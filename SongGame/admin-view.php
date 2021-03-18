<!DOCTYPE html>
<html lang="de">
    <?php
        $conn = new mysqli("localhost", "root", "password", "song_game");
        if (!$conn->connect_error) {
            //die("Connection failed: " . $conn->connect_error);
            if(isset($_GET['deleteAll']) && $_GET['deleteAll'] == "yes"){
                $queryDeleteAll = "TRUNCATE TABLE songs";
                $resultDeleteAll = $conn->query($queryDeleteAll);
                if ($conn->query($queryDeleteAll) === TRUE) {
                    //Alle erfolgreich gelöscht
                }
            }

            $queryListAll = "SELECT youtube_link FROM songs";
            $resultListAll = $conn->query($queryListAll);        
        }
        $conn->close();
    ?>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="https://unpkg.com/purecss@1.0.1/build/base-min.css">
        <link rel="stylesheet" href="https://unpkg.com/purecss@2.0.5/build/pure-min.css" integrity="sha384-LTIDeidl25h2dPxrB2Ekgc9c7sEC3CWGM6HeFmuDNUjX76Ert4Z4IY714dhZHPLd" crossorigin="anonymous">
        <link rel="stylesheet" type="text/css" href="style.css" media="screen" />
        <title>Song Spiel - AdminView</title>
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
            <section>
            <h2>Momentan existieren folgende Links in der Datenbank:</h2>
                <?php
                    if (isset($resultListAll)){
                        echo "<ul>";
                        foreach ($resultListAll as $link) {
                            echo "<li>".$link['youtube_link']."</li>";
                        }
                        echo "</ul>";
                    }
                ?>
            </section>
            <form action="admin-view.php">
                <button class="button-error pure-button" name="deleteAll" type="submit" value="yes">ALLE BISHER ABGEGEBENEN LINKS LÖSCHEN</button>
            </form>
        </main>
        <footer>

        </footer>
    </div>
    </body>
</html>