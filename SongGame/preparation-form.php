<!DOCTYPE html>
<html lang="de">
    <?php
        if(isset($_GET['link']) && !empty($_GET['link'])){
            $link = $_GET['link'];

            $conn = new mysqli("localhost", "root", "password", "song_game");
            if ($conn->connect_error) {
                //die("Connection failed: " . $conn->connect_error);
              } 
            $query = "INSERT INTO songs (youtube_link) VALUES ('$link')";
            if ($conn->query($query) === TRUE) {
                //echo "New record created successfully";
              } else {
                //echo "Error: " . $query . "<br>" . $conn->error;
              }
              
              $conn->close();
        }
    ?>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
              <a class="pure-menu-link" href="preparation-form.php">Eingabe</a></li>
            <li class="pure-menu-item">
              <a class="pure-menu-link" href="game.php">Spiel</a></li>
          </ul>
        </nav>
    </header>
    <main>
        <form action="preparation-form.php" class="pure-form pure-form-stacked">
            <input autofocus type="text" id="link" name="link" placeholder="Youtube-Link">
            <button class="pure-button pure-button-primary" type="submit" value="Submit">Abschicken</button>
        </form>

        <?php if(isset($_GET['link']) && !empty($_GET['link'])){ ?>
            <p> Der Link ist jetzt im Lostopf! </p>
        <?php } ?>

        <h3>Ablauf</h3>
        <ol>
          <li>Jeder sucht sich eine bestimmte Anzahl Liedern, die per Youtube-Link oben abgegeben werden.</li>
          <li>Nachdem alle Links gesammelt wurden, wird <strong>ein</strong> Teilnehmer bestimmt der auf die "Spiel"-Seite geht.</li>
          <li>Auf der "Spiel"-Seite können immer mit Klick auf "Nächster Link" alle Links in zufälliger Reihenfolge abgespielt werden. <strong>Jeder Link der dran war, wird direkt gelöscht!</strong></li>
          <li>Viel Freude beim Spielen!</li>
        </ol>
    </main>
    <footer>

    </footer>
    </div>
  </body>
</html>