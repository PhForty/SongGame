/*
 * Minimal QR Code generator for SongGame.
 *
 * Byte mode, error correction level M, versions 1-15 (up to ~400 bytes) —
 * plenty for a join URL. Implements ISO/IEC 18004; no dependencies, so the
 * game keeps working on a LAN without internet access.
 *
 * Written in ES5 so it also runs under cscript for the test harness.
 *
 *   QRCode.encode(text)       -> { size: n, modules: [[0|1, ...], ...] }
 *   QRCode.toSvg(text, opts)  -> SVG markup string
 */
var QRCode = (function () {
    'use strict';

    // ===== GF(256), primitive polynomial x^8+x^4+x^3+x^2+1 (0x11D) =====
    var EXP = [], LOG = [];
    (function () {
        var x = 1;
        for (var i = 0; i < 255; i++) {
            EXP[i] = x;
            LOG[x] = i;
            x <<= 1;
            if (x & 0x100) x ^= 0x11D;
        }
        for (var j = 255; j < 512; j++) EXP[j] = EXP[j - 255];
    })();

    function gmul(a, b) {
        if (a === 0 || b === 0) return 0;
        return EXP[LOG[a] + LOG[b]];
    }

    // ===== Block layout for ECC level M =====
    // [eccPerBlock, blocksGroup1, dataPerBlockGroup1, blocksGroup2, dataPerBlockGroup2]
    var ECC_M = [
        null,
        [10, 1, 16, 0, 0],
        [16, 1, 28, 0, 0],
        [26, 1, 44, 0, 0],
        [18, 2, 32, 0, 0],
        [24, 2, 43, 0, 0],
        [16, 4, 27, 0, 0],
        [18, 4, 31, 0, 0],
        [22, 2, 38, 2, 39],
        [22, 3, 36, 2, 37],
        [26, 4, 43, 1, 44],
        [30, 1, 50, 4, 51],
        [22, 6, 36, 2, 37],
        [22, 8, 37, 1, 38],
        [24, 4, 40, 5, 41],
        [24, 5, 41, 5, 42]
    ];

    var ALIGN = [
        null, [], [6, 18], [6, 22], [6, 26], [6, 30], [6, 34],
        [6, 22, 38], [6, 24, 42], [6, 26, 46], [6, 28, 50],
        [6, 30, 54], [6, 32, 58], [6, 34, 62], [6, 26, 46, 66], [6, 26, 48, 70]
    ];

    var MAX_VERSION = 15;

    // ===== Reed-Solomon =====
    function rsGenPoly(degree) {
        var poly = [1];
        for (var i = 0; i < degree; i++) {
            // poly *= (x + a^i), coefficients in descending degree order
            var next = [];
            for (var k = 0; k <= poly.length; k++) next.push(0);
            for (var j = 0; j < poly.length; j++) {
                next[j] ^= poly[j];                 // * x
                next[j + 1] ^= gmul(poly[j], EXP[i]); // * a^i
            }
            poly = next;
        }
        return poly;
    }

    function rsRemainder(data, gen, eccLen) {
        var buf = data.slice(0), i;
        for (i = 0; i < eccLen; i++) buf.push(0);
        for (i = 0; i < data.length; i++) {
            var factor = buf[i];
            if (factor === 0) continue;
            for (var j = 1; j < gen.length; j++) buf[i + j] ^= gmul(gen[j], factor);
        }
        return buf.slice(data.length);
    }

    // ===== Data encoding =====
    function toUtf8(str) {
        var out = [];
        for (var i = 0; i < str.length; i++) {
            var c = str.charCodeAt(i);
            if (c < 0x80) {
                out.push(c);
            } else if (c < 0x800) {
                out.push(0xC0 | (c >> 6), 0x80 | (c & 63));
            } else if (c >= 0xD800 && c <= 0xDBFF && i + 1 < str.length) {
                var cp = 0x10000 + ((c - 0xD800) << 10) + (str.charCodeAt(++i) - 0xDC00);
                out.push(0xF0 | (cp >> 18), 0x80 | ((cp >> 12) & 63),
                         0x80 | ((cp >> 6) & 63), 0x80 | (cp & 63));
            } else {
                out.push(0xE0 | (c >> 12), 0x80 | ((c >> 6) & 63), 0x80 | (c & 63));
            }
        }
        return out;
    }

    function dataCapacity(version) {
        var t = ECC_M[version];
        return t[1] * t[2] + t[3] * t[4];
    }

    function pickVersion(byteLen, minVersion) {
        for (var v = minVersion || 1; v <= MAX_VERSION; v++) {
            var ccBits = v <= 9 ? 8 : 16;
            if (dataCapacity(v) * 8 >= 4 + ccBits + byteLen * 8) return v;
        }
        throw new Error('QRCode: data too long (' + byteLen + ' bytes)');
    }

    function buildDataCodewords(bytes, version) {
        var total = dataCapacity(version);
        var bits = [];
        function push(val, len) {
            for (var i = len - 1; i >= 0; i--) bits.push((val >>> i) & 1);
        }
        push(4, 4); // byte mode indicator
        push(bytes.length, version <= 9 ? 8 : 16);
        for (var i = 0; i < bytes.length; i++) push(bytes[i], 8);
        push(0, Math.min(4, total * 8 - bits.length));      // terminator
        push(0, (8 - bits.length % 8) % 8);                 // pad to byte boundary

        var cw = [];
        for (var b = 0; b < bits.length; b += 8) {
            var v = 0;
            for (var k = 0; k < 8; k++) v = (v << 1) | bits[b + k];
            cw.push(v);
        }
        var pad = [0xEC, 0x11], p = 0;
        while (cw.length < total) cw.push(pad[p++ % 2]);
        return cw;
    }

    function interleave(dataCw, version) {
        var t = ECC_M[version];
        var eccLen = t[0], n1 = t[1], d1 = t[2], n2 = t[3], d2 = t[4];
        var gen = rsGenPoly(eccLen);
        var blocks = [], eccBlocks = [], off = 0, i, b;

        for (i = 0; i < n1 + n2; i++) {
            var len = i < n1 ? d1 : d2;
            var blk = dataCw.slice(off, off + len);
            off += len;
            blocks.push(blk);
            eccBlocks.push(rsRemainder(blk, gen, eccLen));
        }

        var out = [], maxLen = d2 > d1 ? d2 : d1;
        for (i = 0; i < maxLen; i++)
            for (b = 0; b < blocks.length; b++)
                if (i < blocks[b].length) out.push(blocks[b][i]);
        for (i = 0; i < eccLen; i++)
            for (b = 0; b < eccBlocks.length; b++) out.push(eccBlocks[b][i]);
        return out;
    }

    // ===== Matrix =====
    function Matrix(version) {
        this.version = version;
        this.size = version * 4 + 17;
        this.modules = [];
        this.isFunction = [];
        for (var y = 0; y < this.size; y++) {
            var row = [], fn = [];
            for (var x = 0; x < this.size; x++) { row.push(0); fn.push(0); }
            this.modules.push(row);
            this.isFunction.push(fn);
        }
    }

    Matrix.prototype.setFn = function (x, y, v) {
        if (x < 0 || y < 0 || x >= this.size || y >= this.size) return;
        this.modules[y][x] = v;
        this.isFunction[y][x] = 1;
    };

    Matrix.prototype.drawFunctionPatterns = function () {
        var size = this.size, i;

        // Timing patterns
        for (i = 0; i < size; i++) {
            this.setFn(6, i, i % 2 === 0 ? 1 : 0);
            this.setFn(i, 6, i % 2 === 0 ? 1 : 0);
        }

        // Finder patterns with separators
        var centers = [[3, 3], [size - 4, 3], [3, size - 4]];
        for (i = 0; i < centers.length; i++) {
            for (var dy = -4; dy <= 4; dy++) {
                for (var dx = -4; dx <= 4; dx++) {
                    var d = Math.max(Math.abs(dx), Math.abs(dy));
                    this.setFn(centers[i][0] + dx, centers[i][1] + dy, (d !== 2 && d !== 4) ? 1 : 0);
                }
            }
        }

        // Alignment patterns (skipping the three finder corners)
        var ap = ALIGN[this.version], last = ap.length - 1;
        for (var a = 0; a <= last; a++) {
            for (var b = 0; b <= last; b++) {
                if ((a === 0 && b === 0) || (a === 0 && b === last) || (a === last && b === 0)) continue;
                for (var ay = -2; ay <= 2; ay++) {
                    for (var ax = -2; ax <= 2; ax++) {
                        this.setFn(ap[b] + ax, ap[a] + ay,
                            Math.max(Math.abs(ax), Math.abs(ay)) !== 1 ? 1 : 0);
                    }
                }
            }
        }

        this.drawFormatBits(0); // placeholder, reserves the modules
        this.drawVersionBits();
    };

    Matrix.prototype.drawFormatBits = function (mask) {
        var size = this.size, i;
        var data = (0 << 3) | mask;  // ECC level M == 0b00
        var rem = data;
        for (i = 0; i < 10; i++) rem = (rem << 1) ^ ((rem >>> 9) * 0x537);
        var bits = ((data << 10) | rem) ^ 0x5412;
        function bit(n) { return (bits >>> n) & 1; }

        for (i = 0; i <= 5; i++) this.setFn(8, i, bit(i));
        this.setFn(8, 7, bit(6));
        this.setFn(8, 8, bit(7));
        this.setFn(7, 8, bit(8));
        for (i = 9; i < 15; i++) this.setFn(14 - i, 8, bit(i));

        for (i = 0; i < 8; i++) this.setFn(size - 1 - i, 8, bit(i));
        for (i = 8; i < 15; i++) this.setFn(8, size - 15 + i, bit(i));
        this.setFn(8, size - 8, 1); // always dark
    };

    Matrix.prototype.drawVersionBits = function () {
        if (this.version < 7) return;
        var rem = this.version;
        for (var i = 0; i < 12; i++) rem = (rem << 1) ^ ((rem >>> 11) * 0x1F25);
        var bits = (this.version << 12) | rem;
        for (i = 0; i < 18; i++) {
            var b = (bits >>> i) & 1;
            var a = this.size - 11 + i % 3;
            var c = Math.floor(i / 3);
            this.setFn(a, c, b);
            this.setFn(c, a, b);
        }
    };

    Matrix.prototype.drawCodewords = function (data) {
        var size = this.size, i = 0;
        for (var right = size - 1; right >= 1; right -= 2) {
            if (right === 6) right = 5;
            for (var vert = 0; vert < size; vert++) {
                for (var j = 0; j < 2; j++) {
                    var x = right - j;
                    var upward = ((right + 1) & 2) === 0;
                    var y = upward ? size - 1 - vert : vert;
                    if (!this.isFunction[y][x] && i < data.length * 8) {
                        this.modules[y][x] = (data[i >>> 3] >>> (7 - (i & 7))) & 1;
                        i++;
                    }
                }
            }
        }
    };

    Matrix.prototype.applyMask = function (mask) {
        for (var y = 0; y < this.size; y++) {
            for (var x = 0; x < this.size; x++) {
                if (this.isFunction[y][x]) continue;
                var invert;
                switch (mask) {
                    case 0: invert = (x + y) % 2 === 0; break;
                    case 1: invert = y % 2 === 0; break;
                    case 2: invert = x % 3 === 0; break;
                    case 3: invert = (x + y) % 3 === 0; break;
                    case 4: invert = (Math.floor(x / 3) + Math.floor(y / 2)) % 2 === 0; break;
                    case 5: invert = (x * y) % 2 + (x * y) % 3 === 0; break;
                    case 6: invert = ((x * y) % 2 + (x * y) % 3) % 2 === 0; break;
                    default: invert = ((x + y) % 2 + (x * y) % 3) % 2 === 0; break;
                }
                if (invert) this.modules[y][x] ^= 1;
            }
        }
    };

    // ===== Mask penalty (ISO/IEC 18004 section 8.8.2) =====
    var N1 = 3, N2 = 3, N3 = 40, N4 = 10;

    Matrix.prototype.penalty = function () {
        var size = this.size, result = 0, x, y;

        function addHistory(run, history, sizeRef) {
            if (history[0] === 0) run += sizeRef; // light border before the first run
            history.unshift(run);
            history.pop();
        }
        function countPatterns(h) {
            var n = h[1];
            var core = n > 0 && h[2] === n && h[3] === n * 3 && h[4] === n && h[5] === n;
            return (core && h[0] >= n * 4 && h[6] >= n ? 1 : 0) +
                   (core && h[6] >= n * 4 && h[0] >= n ? 1 : 0);
        }
        function terminate(color, run, history, sizeRef) {
            if (color) { addHistory(run, history, sizeRef); run = 0; }
            addHistory(run + sizeRef, history, sizeRef);
            return countPatterns(history);
        }
        function newHistory() { return [0, 0, 0, 0, 0, 0, 0]; }

        // Rows
        for (y = 0; y < size; y++) {
            var runColor = 0, runLen = 0, hist = newHistory();
            for (x = 0; x < size; x++) {
                if (this.modules[y][x] === runColor) {
                    runLen++;
                    if (runLen === 5) result += N1;
                    else if (runLen > 5) result++;
                } else {
                    addHistory(runLen, hist, size);
                    if (!runColor) result += countPatterns(hist) * N3;
                    runColor = this.modules[y][x];
                    runLen = 1;
                }
            }
            result += terminate(runColor, runLen, hist, size) * N3;
        }
        // Columns
        for (x = 0; x < size; x++) {
            var runColorC = 0, runLenC = 0, histC = newHistory();
            for (y = 0; y < size; y++) {
                if (this.modules[y][x] === runColorC) {
                    runLenC++;
                    if (runLenC === 5) result += N1;
                    else if (runLenC > 5) result++;
                } else {
                    addHistory(runLenC, histC, size);
                    if (!runColorC) result += countPatterns(histC) * N3;
                    runColorC = this.modules[y][x];
                    runLenC = 1;
                }
            }
            result += terminate(runColorC, runLenC, histC, size) * N3;
        }
        // 2x2 blocks of the same colour
        for (y = 0; y < size - 1; y++) {
            for (x = 0; x < size - 1; x++) {
                var c = this.modules[y][x];
                if (c === this.modules[y][x + 1] && c === this.modules[y + 1][x] && c === this.modules[y + 1][x + 1])
                    result += N2;
            }
        }
        // Dark/light balance
        var dark = 0;
        for (y = 0; y < size; y++) for (x = 0; x < size; x++) dark += this.modules[y][x];
        var total = size * size;
        var k = Math.ceil((Math.abs(dark * 20 - total * 10)) / total) - 1;
        result += k * N4;
        return result;
    };

    // ===== Public API =====
    function encode(text, opts) {
        opts = opts || {};
        var bytes = toUtf8(String(text));
        var version = pickVersion(bytes.length, opts.minVersion);
        var codewords = interleave(buildDataCodewords(bytes, version), version);

        var m = new Matrix(version);
        m.drawFunctionPatterns();
        m.drawCodewords(codewords);

        var chosen = opts.mask;
        if (chosen === undefined || chosen === null) {
            var best = -1, bestScore = Infinity;
            for (var mask = 0; mask < 8; mask++) {
                m.applyMask(mask);
                m.drawFormatBits(mask);
                var score = m.penalty();
                if (score < bestScore) { bestScore = score; best = mask; }
                m.applyMask(mask); // undo (XOR is its own inverse)
            }
            chosen = best;
        }
        m.applyMask(chosen);
        m.drawFormatBits(chosen);

        return { size: m.size, version: version, mask: chosen, modules: m.modules };
    }

    /**
     * Renders the code as standalone SVG markup.
     * opts: { size (px, default 256), margin (modules, default 4),
     *         dark, light (CSS colours) }
     */
    function toSvg(text, opts) {
        opts = opts || {};
        var qr = encode(text, opts);
        var margin = opts.margin === undefined ? 4 : opts.margin;
        var dim = qr.size + margin * 2;
        var px = opts.size || 256;
        var dark = opts.dark || '#000000';
        var light = opts.light || '#ffffff';

        var path = [];
        for (var y = 0; y < qr.size; y++) {
            for (var x = 0; x < qr.size; x++) {
                if (qr.modules[y][x]) path.push('M' + (x + margin) + ',' + (y + margin) + 'h1v1h-1z');
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' + px + '" height="' + px +
            '" viewBox="0 0 ' + dim + ' ' + dim + '" shape-rendering="crispEdges" role="img">' +
            '<rect width="' + dim + '" height="' + dim + '" fill="' + light + '"/>' +
            '<path fill="' + dark + '" d="' + path.join('') + '"/></svg>';
    }

    return { encode: encode, toSvg: toSvg, MAX_VERSION: MAX_VERSION };
})();

if (typeof window !== 'undefined') window.QRCode = QRCode;
