# Criteris d’acceptació de les Històries d’Usuari del MVP

Aquest document recull les històries d’usuari principals del MVP i dos criteris d’acceptació per a cadascuna.  
Els criteris segueixen el format **Donat / Quan / Llavors** per facilitar la seva verificació.

---

## Taula de criteris d’acceptació

| ID | Història d’usuari | Criteri d’acceptació 1 | Criteri d’acceptació 2 |
|---|---|---|---|
| **US001** | Com a **usuari normal**, vull visualitzar en un mapa els esdeveniments, incidències i activitats que estan ocorrent a prop meu, per estar informat sobre què passa a la ciutat en temps real. | **Donat** que l’usuari ha iniciat sessió a l’aplicació,<br>**quan** accedeix a la pantalla principal del mapa,<br>**llavors** el sistema mostra marcadors visibles amb els esdeveniments, incidències i activitats disponibles a la zona. | **Donat** que hi ha marcadors visibles al mapa,<br>**quan** l’usuari selecciona un marcador,<br>**llavors** el sistema mostra una targeta informativa amb el títol, la descripció, la ubicació, l’hora, la categoria i l’usuari que ha publicat l’esdeveniment o incidència. |
| **US002** | Com a **usuari normal**, vull poder consultar el perfil de l’usuari que ha publicat un esdeveniment o incidència, per valorar si la informació és fiable. | **Donat** que l’usuari està visualitzant la informació d’un esdeveniment o incidència,<br>**quan** prem sobre el nom o la imatge del creador,<br>**llavors** el sistema obre el perfil públic del creador. | **Donat** que l’usuari ha accedit al perfil públic d’un creador,<br>**quan** es mostra la pantalla del perfil,<br>**llavors** el sistema mostra la seva valoració mitjana, reputació i historial públic de publicacions. |
| **US003** | Com a **usuari normal**, vull poder unir-me a una activitat publicada al mapa, per participar-hi sense haver de contactar manualment amb l’organitzador. | **Donat** que l’usuari està visualitzant una activitat disponible,<br>**quan** prem el botó **“Unir-me”**,<br>**llavors** el sistema registra la seva inscripció a l’activitat. | **Donat** que l’usuari s’ha unit correctament a una activitat,<br>**quan** torna a consultar la targeta de l’activitat,<br>**llavors** el sistema mostra l’estat **“Inscrit”** i no descompta cap token del seu compte. |
| **US004** | Com a **usuari normal**, vull poder actualitzar o confirmar informació sobre una incidència o esdeveniment, per ajudar altres usuaris a tenir informació més precisa i actualitzada. | **Donat** que l’usuari està visualitzant una incidència o esdeveniment publicat,<br>**quan** afegeix una actualització, comentari o confirmació,<br>**llavors** el sistema guarda la nova informació associada a aquella publicació. | **Donat** que una actualització s’ha guardat correctament,<br>**quan** altres usuaris consulten la mateixa incidència o esdeveniment,<br>**llavors** el sistema mostra la informació actualitzada sense que s’hagin descomptat tokens a l’usuari que l’ha aportada. |
| **US005** | Com a **usuari Pro**, vull poder crear esdeveniments lucratius o promocionats, per donar visibilitat a activitats comercials dins del mapa de la ciutat. | **Donat** que l’usuari té una subscripció Pro activa,<br>**quan** accedeix a la interfície Pro i selecciona l’opció **“Crear esdeveniment lucratiu”**,<br>**llavors** el sistema mostra un formulari amb els camps necessaris per definir l’esdeveniment: títol, descripció, ubicació, data, hora, categoria, aforament i informació comercial. | **Donat** que l’usuari Pro ha completat el formulari de l’esdeveniment lucratiu,<br>**quan** prem el botó **“Publicar”**,<br>**llavors** el sistema calcula i mostra el cost en tokens abans de confirmar la publicació. |
| **US006** | Com a **usuari Pro**, vull que el sistema comprovi el meu saldo de tokens abans de publicar un esdeveniment lucratiu, per assegurar que només es publiquen activitats quan hi ha saldo suficient. | **Donat** que l’usuari Pro té tokens suficients,<br>**quan** confirma la publicació d’un esdeveniment lucratiu,<br>**llavors** el sistema descompta els tokens corresponents i publica l’esdeveniment al mapa. | **Donat** que l’usuari Pro no té tokens suficients,<br>**quan** intenta confirmar la publicació d’un esdeveniment lucratiu,<br>**llavors** el sistema bloqueja la publicació i mostra un missatge indicant que necessita més tokens o millorar el seu pla. |
| **US007** | Com a **usuari Pro**, vull poder veure quins usuaris s’han inscrit als meus esdeveniments, per gestionar millor l’assistència i l’organització de l’activitat. | **Donat** que l’usuari Pro ha publicat un esdeveniment i hi ha usuaris inscrits,<br>**quan** accedeix a l’apartat de gestió de l’esdeveniment,<br>**llavors** el sistema mostra una llista amb els usuaris inscrits. | **Donat** que el sistema mostra la llista d’inscrits,<br>**quan** l’usuari Pro consulta aquesta informació,<br>**llavors** cada inscrit es mostra amb el seu nom de perfil, valoració i informació pública disponible. |
| **US008** | Com a **usuari Pro**, vull poder editar o cancel·lar els meus esdeveniments publicats, per mantenir la informació actualitzada o retirar activitats que ja no es faran. | **Donat** que l’usuari Pro té un esdeveniment actiu publicat,<br>**quan** selecciona l’opció **“Editar esdeveniment”**,<br>**llavors** el sistema permet modificar les dades principals de l’esdeveniment: títol, descripció, ubicació, data, hora, categoria o aforament. | **Donat** que l’usuari Pro té un esdeveniment actiu publicat,<br>**quan** selecciona l’opció **“Cancel·lar esdeveniment”**,<br>**llavors** el sistema retira l’esdeveniment del mapa i informa els usuaris inscrits de la cancel·lació. |

---

## Resum de funcionalitats del MVP

| Tipus d’usuari | Funcionalitats principals |
|---|---|
| **Usuari normal** | Visualitzar esdeveniments i incidències al mapa, consultar perfils, unir-se a activitats i actualitzar informació comunitària. |
| **Usuari Pro** | Crear esdeveniments lucratius, publicar activitats amb tokens, gestionar esdeveniments, veure inscrits i editar o cancel·lar activitats. |

---

## Notes

- Les accions comunitàries, com unir-se a una activitat o actualitzar una incidència, **no consumeixen tokens**.
- Les activitats lucratives o promocionades **sí consumeixen tokens**.
- El cost en tokens depèn de la mida, visibilitat o finalitat comercial de l’esdeveniment.
- La interfície de l’usuari Pro és diferent de la de l’usuari normal perquè inclou eines de gestió i promoció.
