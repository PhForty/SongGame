<!DOCTYPE html>
<html lang="de">
    <?php
        $row;
        $conn = new mysqli("localhost", "root", "password", "song_game");
        if ($conn->connect_error) {
          //die("Connection failed: " . $conn->connect_error);
        } 
        if(isset($_GET['next']) && $_GET['next'] == "yes"){
            $query = "SELECT youtube_link FROM songs ORDER BY RAND() LIMIT 1";
            $result = $conn->query($query);
            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc();
                $queryDelete = "DELETE FROM songs WHERE youtube_link='".$row['youtube_link']."' LIMIT 1";
                $conn->query($queryDelete);
              }
        }
    ?>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/purecss@1.0.1/build/base-min.css">
    <link rel="stylesheet" href="https://unpkg.com/purecss@2.0.5/build/pure-min.css" integrity="sha384-LTIDeidl25h2dPxrB2Ekgc9c7sEC3CWGM6HeFmuDNUjX76Ert4Z4IY714dhZHPLd" crossorigin="anonymous">
    <link rel="stylesheet" type="text/css" href="style.css" media="screen" />
    <script>
        function copyToClipboard(link) {
          navigator.clipboard.writeText(link);
        }
    </script>
    <title>Song Spiel - Hauptseite</title>
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
        <?php if(isset($_GET['next']) && !empty($_GET['next']) && isset($row["youtube_link"]) && !empty($row["youtube_link"])) {
          //Falls Pattern extrahiert werden kann
          $pattern = "/(\/|%3D|v=)([0-9A-z-_]{11})([%#?&]|$)/";
          if(preg_match($pattern, $row["youtube_link"])){
            preg_match($pattern, $row["youtube_link"], $match);
            $embedLink = "https://www.youtube.com/embed/$match[2]";
            echo "<div id='videocontainer'><iframe src='$embedLink' frameborder='0' allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture' allowfullscreen></iframe></div>";
          } 
          else {
            echo "<p>Der Link kann leider nicht korrekt verarbeitet werden. <br> Bitte kopieren und selber im neuen Tab öffnen.</p>";
          }
        } ?>
        <?php
          if(isset($_GET['next']) && !empty($_GET['next']) && isset($row["youtube_link"]) && !empty($row["youtube_link"]))
            echo "<p>Der Youtube Link ist: <a href='".$row["youtube_link"]."' target='_blank'>".$row["youtube_link"]."</a></p>";
        ?>
        <form class="pure-form pure-form-aligned" action="game.php">
            <button class="pure-button pure-button-primary" name="next" type="submit" value="yes">Nächster Link</button>
        </form>
        <?php
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
        ?>
        
    </main>
    <footer>

    </footer>
  </div>
  </body>
</html>