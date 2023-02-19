<?php
session_start();
include 'db-connect.php';

if(!isset($_SESSION['StartedGame']) || (isset($_SESION['StartedGame']) && $_SESSION['StartedGame'] == 0)) {
  header('Location: index');
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/github-fork-ribbon-css/0.2.3/gh-fork-ribbon.min.css" />
    <link rel="stylesheet" type="text/css" href="pure-min.css" media="screen" />
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
    <a class="github-fork-ribbon left-bottom" href="https://github.com/PhForty/SongGame" data-ribbon="Fork me on GitHub" title="Fork me on GitHub">
        Fork me on GitHub 
</a>
  </div>
  </body>
</html>