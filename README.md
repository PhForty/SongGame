# SongGame
Kleine WebApp mit mysql Datenbank für das Songspiel. Sammelt anonym Links und spielt Youtube Videos ab.

# Install
## Docker
```
git clone https://github.com/PhForty/SongGame.git
cd SongGame
docker-compose up
```
Dann im Browser https://localhost:80 öffnen

## Manuell
Git Repo klonen
Webserver aufsetzen (z.B. Apache) - src/ dort reinkopieren
Datenbank aufsetzen (Mit passendem User und Namen, s. PHP Files)

# Todos
* [ ] Support für gleichzeitige Nutzung mehrerer Gruppen
* [ ] Output Liste am Ende von allen Links -> Für Interessierte
* [ ] Counter auf Eingabe-Seite für Info
* [ ] Spotify Embedded Player
* [ ] Adminbereich besser schützen (z.B. nur einsehbar mit bekanntem Gruppencode?)
* [ ] Refactor for readability + maintainability
* [x] ~~404 Page~~
* [x] ~~Link auf Github-Projekt einfügen~~

# Quellen
Github Ribbon von [https://github.com/tholman/github-corners](Tholman); [MIT License](https://github.com/tholman/github-corners/blob/master/license.md)