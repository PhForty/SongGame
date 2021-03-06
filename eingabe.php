<!DOCTYPE html>
<html lang="de">
    <?php
    error_reporting(0);
      $conn = new mysqli("127.0.0.1", "song_user", "wrjkn422", "song_game");
      if ($conn->connect_error) {
        echo "Die Datenbankverbindung hat leider nicht geklappt.";
      } else {
        if(isset($_POST['link']) && !empty($_POST['link'])){
          $link = $_POST['link'];
          $query = $conn->prepare("INSERT INTO songs (youtube_link) VALUES (?)");
          $query->bind_param("s",$link);
          $query->execute();
          
          $conn->close();
        }
      }
        
    ?>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon.ico">
    <link rel="stylesheet" href="https://unpkg.com/purecss@1.0.1/build/base-min.css">
    <link rel="stylesheet" href="https://unpkg.com/purecss@2.0.5/build/pure-min.css" integrity="sha384-LTIDeidl25h2dPxrB2Ekgc9c7sEC3CWGM6HeFmuDNUjX76Ert4Z4IY714dhZHPLd" crossorigin="anonymous">
    <link rel="stylesheet" type="text/css" href="style.css" media="screen" />
    <title>Song Spiel - Abgabe</title>
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
          </ul>
        </nav>
    </header>
    <main>
        <form action="eingabe.php" class="pure-form pure-form-stacked" method="post">
            <input autofocus type="text" id="link" name="link" placeholder="Youtube-Link">
            <button class="pure-button pure-button-primary" type="submit" value="Submit">Abschicken</button>
        </form>

        <?php if(isset($_POST['link']) && !empty($_POST['link'])){ ?>
            <p> Der Link ist jetzt im Lostopf! </p>
        <?php } ?>

        <h3>Ablauf</h3>
        <ol>
          <li>Jeder sucht sich eine bestimmte Anzahl an Liedern, die jeder geheim f??r sich per Youtube-Link oben abgibt (Timestamps werden unterst??tzt).</li>
          <li>Nachdem alle Links gesammelt wurden, wird <strong>ein</strong> Teilnehmer bestimmt, der auf die "Spiel"-Seite geht.</li>
          <li>Auf der "Spiel"-Seite k??nnen immer mit Klick auf "N??chster Link" alle Links in zuf??lliger Reihenfolge abgespielt werden. <strong>Jeder Link, der dran war, wird direkt gel??scht!</strong></li>
          <li>Viel Freude beim Spielen!</li>
        </ol>
    </main>
    <footer>

    </footer>
    </div>
  </body>
</html>