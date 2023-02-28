var coll = document.getElementsByClassName("collapsible");
var i;
for (i = 0; i < coll.length; i++) {
  coll[i].addEventListener("click", function() {
    this.classList.toggle("active");
    var content = this.nextElementSibling;
    if (content.style.display === "block") {
      content.style.display = "none";
    } else {
      content.style.display = "block";
    }
  });
}

if(document.getElementById("lostopftext")){
  var lostopf = document.getElementById("lostopftext");
  var myCanvas = document.createElement('canvas');
  myCanvas.height = 300;
  lostopf.prepend(myCanvas);
  var myConfetti = confetti.create(myCanvas, {});
  myConfetti({
    // any options from the global confetti function
    gravity: 2
  });
  setTimeout(() => {
    myConfetti.reset();
  }, 1200);
}
