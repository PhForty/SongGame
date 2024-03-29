<?php
session_start();
include 'db-connect.php';

if(!isset($_SESSION['StartedGame']) || (isset($_SESSION['StartedGame']) && $_SESSION['StartedGame'] === 0) || (isset($_SESSION['isHost']) && !$_SESSION['isHost'])) {
  header('Location: eingabe');
}

//Lade den naechsten Song
if(isset($_POST['next']) && $_POST['next'] == "yes"){
  $query = $conn->prepare("SELECT youtube_link FROM songs WHERE `SpielID` = ? AND wasViewed = 0 ORDER BY RAND() LIMIT 1");
  $query->bind_param("s",$_SESSION['SpielID']);
  $query->execute();
  $result = $query->get_result();
  $row;
  if ($result->num_rows == 1) {
      $row = $result->fetch_assoc();
      $queryDelete = $conn->prepare("UPDATE songs SET wasViewed=1  WHERE youtube_link=? AND `SpielID` = ?");
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/purecss@3.0.0/build/base-min.css">
    <link rel="stylesheet" type="text/css" href="pure-min.css" media="screen" />
    <link rel="stylesheet" type="text/css" href="style.css" media="screen" />
    <title>Spiel</title>
  </head>
  <body>
  <div id="wrapper">
    <header>
      <nav class="pure-menu pure-menu-horizontal">
          <ul class="pure-menu-list">
            <?php
            if(isset($_SESSION['isHost']) && $_SESSION['isHost']){
              echo "<li class='pure-menu-item'>";
              echo "<a class='pure-menu-link' href='eingabe'>Eingabe</a></li>";
              echo "<li class='pure-menu-item'>";
              echo "<a class='pure-menu-link' href='viewer'>Spiel</a></li>";
              echo "<li class='pure-menu-item'>";
              echo "<a class='pure-menu-link' href='admin-view'>Admin-View</a></li>";
            }
            ?>
            <li class="pure-menu-item">
            <a class="pure-menu-link" href="logout">Logout</a></li>
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
        <form class="pure-form pure-form-aligned" action="viewer" method="post">
            <button class="pure-button pure-button-primary" name="next" type="submit" value="yes">Nächster Link</button>
        </form>
        <?php
          $query = $conn->prepare("SELECT COUNT(*) FROM songs WHERE `SpielID` = ? and wasViewed = 0");
          $query->bind_param("s",$_SESSION['SpielID']);
          $query->execute();
          $result = $query->get_result();
          if($result!==false){
            $row = $result->fetch_assoc();
            if($row['COUNT(*)']!=0){
              echo "<p>Verbleibende Videos: ".$row['COUNT(*)']." </p>";
            }else {
              echo "<p>Es gibt keine Links mehr.</p>";
            }
          }
          $conn->close();


       if(isset($_SESSION['isHost']) && !$_SESSION['isHost']){
        echo "<p>Spiel: <b>{$_SESSION['SpielID']}</b> - Rolle: <b>Teilnehmer</b></p>";
      } else {
        echo "<p>Spiel: <b>{$_SESSION['SpielID']}</b> - Rolle: <b>Gastgeber</b></p>";
      }?>
  </div>
  </body>
</html>