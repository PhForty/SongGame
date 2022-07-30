<?php
session_start();
include 'db-connect.php';

if(!isset($_SESSION['StartedGame']) || (isset($_SESION['StartedGame']) && $_SESSION['StartedGame'] == 0)) {
  header('Location: /index');
}

if(isset($_POST['next']) && $_POST['next'] == "yes"){
  $query = $conn->prepare("SELECT youtube_link FROM songs WHERE `SpielID` = ? ORDER BY RAND() LIMIT 1");
  $query->bind_param("s",$_SESSION['SpielID']);
  $query->execute();
  $result = $query->get_result();
  $row;
  if ($result->num_rows == 1) {
      $row = $result->fetch_assoc();
      $queryDelete = $conn->prepare("DELETE FROM songs WHERE youtube_link=? AND `SpielID` = ? LIMIT 1");
      $queryDelete->bind_param("ss",$row['youtube_link'],$_SESSION['SpielID']);
      $queryDelete->execute();
      
    }
}
?>
<!DOCTYPE html>
<html lang="de">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon.ico">
    <link rel="stylesheet" href="https://unpkg.com/purecss@2.0.5/build/pure-min.css" integrity="sha384-LTIDeidl25h2dPxrB2Ekgc9c7sEC3CWGM6HeFmuDNUjX76Ert4Z4IY714dhZHPLd" crossorigin="anonymous">
    <link rel="stylesheet" type="text/css" href="style.css" media="screen" />
    <title>Spiel</title>
  </head>
  <body>
  <div id="wrapper">
    <header>
    <nav class="pure-menu pure-menu-horizontal">
          <ul class="pure-menu-list">
            <li class="pure-menu-item">
              <a class="pure-menu-link" href="eingabe.php">Eingabe</a></li>
            <li class="pure-menu-item">
              <a class="pure-menu-link" href="game.php">Spiel</a></li>
            <li class="pure-menu-item">
            <a class="pure-menu-link" href="logout.php">Logout</a></li>
          </ul>
          <a href="https://github.com/PhForty/SongGame" class="github-corner" aria-label="View source on GitHub"><svg width="80" height="80" viewBox="0 0 250 250" style="fill:#151513; color:#fff; position: absolute; top: 0; border: 0; right: 0;" aria-hidden="true"><path d="M0,0 L115,115 L130,115 L142,142 L250,250 L250,0 Z"></path><path d="M128.3,109.0 C113.8,99.7 119.0,89.6 119.0,89.6 C122.0,82.7 120.5,78.6 120.5,78.6 C119.2,72.0 123.4,76.3 123.4,76.3 C127.3,80.9 125.5,87.3 125.5,87.3 C122.9,97.6 130.6,101.9 134.4,103.2" fill="currentColor" style="transform-origin: 130px 106px;" class="octo-arm"></path><path d="M115.0,115.0 C114.9,115.1 118.7,116.5 119.8,115.4 L133.7,101.6 C136.9,99.2 139.9,98.4 142.2,98.6 C133.8,88.0 127.5,74.4 143.8,58.0 C148.5,53.4 154.0,51.2 159.7,51.0 C160.3,49.4 163.2,43.6 171.4,40.1 C171.4,40.1 176.1,42.5 178.8,56.2 C183.1,58.6 187.2,61.8 190.9,65.4 C194.5,69.0 197.7,73.2 200.1,77.6 C213.8,80.2 216.3,84.9 216.3,84.9 C212.7,93.1 206.9,96.0 205.4,96.6 C205.1,102.4 203.0,107.8 198.3,112.5 C181.9,128.9 168.3,122.5 157.7,114.1 C157.9,116.9 156.7,120.9 152.7,124.9 L141.0,136.5 C139.8,137.7 141.6,141.9 141.8,141.8 Z" fill="currentColor" class="octo-body"></path></svg></a><style>.github-corner:hover .octo-arm{animation:octocat-wave 560ms ease-in-out}@keyframes octocat-wave{0%,100%{transform:rotate(0)}20%,60%{transform:rotate(-25deg)}40%,80%{transform:rotate(10deg)}}@media (max-width:500px){.github-corner:hover .octo-arm{animation:none}.github-corner .octo-arm{animation:octocat-wave 560ms ease-in-out}}</style>
        </nav>
    </header>
    <main>
        <?php if(isset($_POST['next']) && !empty($_POST['next']) && isset($row["youtube_link"]) && !empty($row["youtube_link"])) {
          $patternID = "/(\/|%3D|v=)([0-9A-z-_]{11})([%#?&]|$)/";
          $patternTimestamp = "/[\?&]t=([0-9]+)/";
          if(preg_match($patternID, $row["youtube_link"])){
            preg_match($patternID, $row["youtube_link"], $matchID); //Regex für Video ID
            preg_match($patternTimestamp, $row["youtube_link"], $matchTimestamp); // Regex für Timestamp
            $embedLink = "https://www.youtube.com/embed/$matchID[2]?start=$matchTimestamp[1]";
            echo "<div id='videocontainer'><iframe src='$embedLink' frameborder='0' allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture' allowfullscreen></iframe></div>";

            echo "<p id='linkinfo'>Klicke <u onClick=\"alert('".htmlentities($row["youtube_link"])."')\">hier</u> um den unverarbeiteten Link zu sehen (nur notwendig, wenn der embedded Player nicht funktioniert).</p>";
          } 
          else {
            echo "<p>Das Video kann nicht angezeigt werden, wahrscheinlich ist es kein Youtube-Link. <br> Bitte kopieren und selber im neuen Tab öffnen: ".htmlentities($row["youtube_link"])."</p>";
          }
        }
        ?>
        <form class="pure-form pure-form-aligned" action="game" method="post">
            <button class="pure-button pure-button-primary" name="next" type="submit" value="yes">Nächster Link</button>
        </form>
        <?php
          $query = $conn->prepare("SELECT COUNT(*) FROM songs WHERE `SpielID` = ?");
          $query->bind_param("s",$_SESSION['SpielID']);
          $query->execute();
          $result = $query->get_result();
          if($result!==false){
            $row = $result->fetch_assoc();
            if($row['COUNT(*)']!=0){
              echo "<p>Momentan gibt es noch ".$row['COUNT(*)']." Links.</p>";
            }else {
              echo "<p>Es gibt keine Links mehr.</p>";
            }
          }
          $conn->close();
        ?>
        <p> Aktuelles Spiel: <strong><?php print($_SESSION['SpielID'])?></strong></p>
    </main>
  </div>
  </body>
</html>