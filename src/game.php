<!DOCTYPE html>
<html lang="de">
    <?php
      try {
        $conn = new mysqli("database", "root", "wrjkn422", "song_game");
      }
      catch (exception $e) {
        echo "Die Datenbankverbindung hat leider nicht geklappt.";
      }
      if(isset($_POST['next']) && $_POST['next'] == "yes"){
        $query = "SELECT youtube_link FROM songs ORDER BY RAND() LIMIT 1";
        $result = $conn->query($query);
        $row;
        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();
            $queryDelete = $conn->prepare("DELETE FROM songs WHERE youtube_link=? LIMIT 1");
            $queryDelete->bind_param("s",$row['youtube_link']);
            $queryDelete->execute();
            
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
    <script>
        function copyToClipboard(link) {
          navigator.clipboard.writeText(link);
        }
    </script>
    <title>Hauptseite</title>
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
        <?php if(isset($_POST['next']) && !empty($_POST['next']) && isset($row["youtube_link"]) && !empty($row["youtube_link"])) {
          $patternID = "/(\/|%3D|v=)([0-9A-z-_]{11})([%#?&]|$)/";
          $patternTimestamp = "/[\?&]t=([0-9]+)/";
          if(preg_match($patternID, $row["youtube_link"])){
            preg_match($patternID, $row["youtube_link"], $matchID); //Regex für Video ID
            preg_match($patternTimestamp, $row["youtube_link"], $matchTimestamp); // Regex für Timestamp
            $embedLink = "https://www.youtube.com/embed/$matchID[2]?start=$matchTimestamp[1]";
            echo "<div id='videocontainer'><iframe src='$embedLink' frameborder='0' allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture' allowfullscreen></iframe></div>";
          } 
          else {
            echo "<p>Der Link kann leider nicht korrekt verarbeitet werden. <br> Bitte kopieren und selber im neuen Tab öffnen.</p>";
          }
        } ?>
        <?php
          if(isset($_POST['next']) && !empty($_POST['next']) && isset($row["youtube_link"]) && !empty($row["youtube_link"])){
            
         echo "<p id='linkinfo'>Klicke <u onClick=\"alert('".htmlentities($row["youtube_link"])."')\">hier</u> um den unverarbeiteten Link zu sehen (nur notwendig, wenn der embedded Player nicht funktioniert).</p>";
          }
          ?>
        <form class="pure-form pure-form-aligned" action="game.php" method="post">
            <button class="pure-button pure-button-primary" name="next" type="submit" value="yes">Nächster Link</button>
        </form>
        <?php
        if ($conn->connect_error) {
          echo "Die Datenbankverbindung hat leider nicht geklappt.";
        } else {
          $query = "SELECT COUNT(*) FROM songs";
          $result = $conn->query($query);
          if($result!==false){
            $row = $result->fetch_assoc();
            if($row['COUNT(*)']!=0){
              echo "<p>Momentan gibt es noch ".$row['COUNT(*)']." Links.</p>";
            }else {
              echo "<p>Es gibt keine Links mehr.</p>";
            }
          }
          $conn->close();
        }
          
        ?>
        
    </main>
    <footer>

    </footer>
  </div>
  </body>
</html>