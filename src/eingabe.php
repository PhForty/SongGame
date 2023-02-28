<?php
session_start();
include 'db-connect.php';

if(!isset($_SESSION['StartedGame']) || (isset($_SESION['StartedGame']) && $_SESSION['StartedGame'] === 0)) {
  header('Location: index');
}

if(isset($_POST['link']) && !empty($_POST['link'])){
  $link = $_POST['link'];
  $query = $conn->prepare("INSERT INTO songs (youtube_link, SpielID) VALUES (?, ?)");
  $query->bind_param("ss",$link, $_SESSION['SpielID']);
  $query->execute();
}

if(isset($_POST['WerdeHost'])){
  //Check if host is actually 0 (to prevent replay attacks)
  $query = $conn->prepare("SELECT hasHost FROM session WHERE `SpielID` = ?");
  $query->bind_param("s",$_SESSION['SpielID']);
  $query->execute();
  $result = $query->get_result();
  $row = $result->fetch_assoc();
  if($row['hasHost']==0) {
    //Set all vars and refresh page
    $_SESSION["isHost"] = true;

    $query = $conn->prepare("UPDATE session SET hasHost=1 WHERE SpielID = ?");
    $query->bind_param("s",$_SESSION['SpielID']);
    $query->execute();
    $conn->close();

    header("Refresh:0");
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
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>
    <title>Eingabe</title>
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
        <?php 
          if(isset($_SESSION['isHost']) && !$_SESSION['isHost']){
            echo "<p>Spiel: <b>{$_SESSION['SpielID']}</b> - Rolle: <b>Teilnehmer</b></p>";
            $query = $conn->prepare("SELECT hasHost FROM session WHERE `SpielID` = ?");
            $query->bind_param("s",$_SESSION['SpielID']);
            $query->execute();
            $result = $query->get_result();
            $row = $result->fetch_assoc();
            if($row['hasHost']==0) {
              echo "<form id='WerdeHost' action='eingabe' class='pure-form' method='post'>";
              echo "<button class='pure-button pure-button-primary pure-u-3-8' type='submit' value='WerdeHost' name='WerdeHost'>Werde Host</button>";
              echo "</form><br><br>";
            }
          } else {
            echo "<p>Spiel: <b>{$_SESSION['SpielID']}</b> - Rolle: <b>Gastgeber</b></p>";
          }
        ?>
        <form action="eingabe" class="pure-form pure-form-stacked" method="post">
            <input autofocus type="text" id="link" name="link" placeholder="Youtube-Link">
            <button class="pure-button pure-button-primary" type="submit" id="AbschickenButton" value="Submit">Abschicken</button>
        </form>
        <?php 
          if(isset($_POST['link']) && !empty($_POST['link'])){
            echo "<div id='lostopftext'> <p>Der Link ist jetzt im Lostopf! </p></div>";
          }
          $query = $conn->prepare("SELECT COUNT(*) FROM songs WHERE `SpielID` = ?");
          $query->bind_param("s",$_SESSION['SpielID']);
          $query->execute();
          $result = $query->get_result();
          if($result!==false){
            $row = $result->fetch_assoc();
            if($row['COUNT(*)']!=0){
              echo "<p>Lieder im Lostopf: ".$row['COUNT(*)']."</p>";
            }else {
              echo "<p>Noch hat niemand ein Lied abgegeben.</p>";
            }
          }
          $conn->close();
        ?>
        <h3>Ablauf</h3>
        <ol>
          <li>Alle können Youtube-Links in den gemeinsamen Topf abgeben</li>
          <li>Der Gastgeber startet das Spiel</li>
          <li>Videos werden in zufälliger Reihenfolge gezeigt und nach dem Abspielen automatisch entfernt</li>
          <li>Feedback gerne per <a href="mailto:contact@songgame.de">Mail</a> oder als <a href="https://github.com/PhForty/SongGame/issues">GitHub Issue</a></li>
        </ol>
    </main>
    </div>
  </body>
  <script src="utilities.js"></script>
</html>