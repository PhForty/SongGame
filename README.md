# SongGame
Kleine WebApp mit mysql Datenbank für das Songspiel. Sammelt anonym Links und spielt Youtube Videos ab.

# Install
## Docker
```
git clone https://github.com/PhForty/SongGame.git
cd SongGame
docker-compose up
```
Dann im Browser https://localhost:80 öffnen

## Manuell
Git Repo klonen
Webserver aufsetzen (z.B. Apache) - src/ dort reinkopieren
Datenbank aufsetzen (Mit passendem User und Namen, s. PHP Files)

## Todos
* Output Liste am Ende von allen Links -> Für Interessierte
* Counter auf Eingabe-Seite für Info
* Link auf Github-Projekt einfügen
* Spotify Embedded Player
* Support für gleichzeitige Nutzung mehrerer Gruppen