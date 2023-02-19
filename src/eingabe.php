<?php
session_start();
include 'db-connect.php';

if(!isset($_SESSION['StartedGame']) || (isset($_SESION['StartedGame']) && $_SESSION['StartedGame'] == 0)) {
  header('Location: index');
}

if(isset($_POST['link']) && !empty($_POST['link'])){
  $link = $_POST['link'];
  $query = $conn->prepare("INSERT INTO songs (youtube_link, SpielID) VALUES (?, ?)");
  $query->bind_param("ss",$link, $_SESSION['SpielID']);
  $query->execute();
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
    <title>Eingabe</title>
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
      
        <form action="eingabe" class="pure-form pure-form-stacked" method="post">
            <input autofocus type="text" id="link" name="link" placeholder="Youtube-Link">
            <button class="pure-button pure-button-primary" type="submit" id="AbschickenButton" value="Submit">Abschicken</button>
        </form>
        <?php 
          if(isset($_POST['link']) && !empty($_POST['link'])){
            echo "<p> Der Link ist jetzt im Lostopf! </p>";
          }
          $query = $conn->prepare("SELECT COUNT(*) FROM songs WHERE `SpielID` = ?");
          $query->bind_param("s",$_SESSION['SpielID']);
          $query->execute();
          $result = $query->get_result();
          if($result!==false){
            $row = $result->fetch_assoc();
            if($row['COUNT(*)']!=0){
              echo "<p>Bereits abgegebene Lieder: ".$row['COUNT(*)']."</p>";
            }else {
              echo "<p>Noch hat niemand ein Lied abgegeben.</p>";
            }
          }
          $conn->close();
        ?>
        <p> Aktuelles Spiel: <strong><?php print($_SESSION['SpielID'])?></strong></p>
        <h3>Ablauf</h3>
        <ol>
          <li>Jeder sucht sich eine bestimmte Anzahl an Liedern, die jeder geheim für sich per Youtube-Link oben abgibt (Timestamps werden unterstützt).</li>
          <li>Nachdem alle Links gesammelt wurden, wird <strong>ein</strong> Teilnehmer bestimmt, der auf die "Spiel"-Seite geht.</li>
          <li>Auf der "Spiel"-Seite können immer mit Klick auf "Nächster Link" alle Links in zufälliger Reihenfolge abgespielt werden. <strong>Jeder Link, der dran war, wird direkt gelöscht!</strong></li>
          <li>Viel Freude beim Spielen!</li>
          <li>Ideen und Wünsche zur Anwendung gerne per <a href="mailto:contact@songgame.de">Mail</a></li>
        </ol>
    </main>
    <a class="github-fork-ribbon left-bottom" href="https://github.com/PhForty/SongGame" data-ribbon="Fork me on GitHub" title="Fork me on GitHub">
        Fork me on GitHub 
</a>
    </div>
  </body>
</html>