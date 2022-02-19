<!DOCTYPE html>
<html lang="de">
    <?php
        try {
            $conn = new mysqli("database", "root", "wrjkn422", "song_game");
          }
        catch (exception $e) {
            echo "Die Datenbankverbindung hat leider nicht geklappt.";
        }
        if(isset($_POST['deleteAll']) && $_POST['deleteAll'] == "yes"){
            $queryDeleteAll = "TRUNCATE TABLE songs";
            $resultDeleteAll = $conn->query($queryDeleteAll);
            if ($conn->query($queryDeleteAll) === TRUE) {
                //Alle erfolgreich gelöscht
            }
        }

        $queryListAll = "SELECT youtube_link FROM songs";
        $resultListAll = $conn->query($queryListAll);        
        $conn->close();
    ?>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="icon" href="favicon.ico">
        <link rel="stylesheet" href="https://unpkg.com/purecss@1.0.1/build/base-min.css">
        <link rel="stylesheet" href="https://unpkg.com/purecss@2.0.5/build/pure-min.css" integrity="sha384-LTIDeidl25h2dPxrB2Ekgc9c7sEC3CWGM6HeFmuDNUjX76Ert4Z4IY714dhZHPLd" crossorigin="anonymous">
        <link rel="stylesheet" type="text/css" href="style.css" media="screen" />
        <title>AdminView</title>
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
            <section>
            <h2>Momentan existieren folgende Links in der Datenbank:</h2>
                <?php
                    if (isset($resultListAll)){
                        echo "<ul>";
                        foreach ($resultListAll as $link) {
                            echo "<li>".htmlspecialchars($link['youtube_link'], ENT_QUOTES)."</li>";
                        }
                        echo "</ul>";
                    }
                ?>
            </section>
            <form action="admin-view.php" method="post">
                <button class="button-error pure-button" name="deleteAll" type="submit" value="yes">ALLE BISHER ABGEGEBENEN LINKS LÖSCHEN</button>
            </form>
        </main>
        <footer>

        </footer>
    </div>
    </body>
</html>