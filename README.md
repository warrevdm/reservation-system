# De Pasto - reservatiesysteem

Maatwerk reservatiemodule voor **De Pasto** in Kapellen. Het systeem is bewust gebouwd zonder maandelijkse SaaS-kost en is geschikt voor klassieke PHP/MySQL-hosting (zoals een gedeelde webhostingomgeving).

De visuele stijl volgt de huisstijlgids van De Pasto: donker olijfgroen `#384510`, saliegroen `#a2b470`, beige `#e1ccb1`, crème `#fffae7`, geel `#ffd863`, Playfair Display voor titels en Montserrat voor lopende tekst. Voor het huisstijlfont **Now** wordt een CSS-fallback voorzien; een gelicentieerde webfont kan later lokaal worden gekoppeld.

## Wat zit er in v1?

### Voor de gast
- Moderne, volledig responsive reservatiewizard.
- Datum, aantal personen en beschikbare tijdsloten.
- Beschikbaarheid op basis van openingsuren, reservatieduur en maximale covers per tijdslot.
- Naam, e-mail, telefoon en opmerkingen (bv. kinderstoel/allergie/verjaardag).
- GDPR-toestemming, honeypot en eenvoudige rate limiting tegen spam.
- Unieke reservatiereferentie.
- Optionele e-mailbevestiging via PHP `mail()`.

### Voor De Pasto
- Beveiligde adminlogin.
- Dagplanning met datumkiezer en kerncijfers.
- **Inbox met nieuwe / nog niet ingedeelde reservaties.**
- **Drag-and-drop:** sleep een reservatie naar de gewenste tafel.
- Klik-eerst-dan-tafel als alternatief op tablet/mobile.
- Conflictcontrole: dezelfde tafel kan niet dubbel geboekt worden op overlappende uren.
- Capaciteitswaarschuwing wanneer een groep groter is dan het aantal plaatsen van een tafel.
- Zones: **Binnen, Terras en Tuin**.
- Visueel tafelplan met ronde, vierkante en rechthoekige tafels.
- **Layoutmodus:** sleep tafels zelf naar hun echte positie; posities worden opgeslagen.
- Tafels toevoegen vanuit de interface.
- Reservaties handmatig toevoegen/bewerken (telefoon, walk-in, enz.).
- Statusflow: Nieuw -> Bevestigd -> Aan tafel -> Afgerond, met Geannuleerd en No-show.
- Openingsuren, boekingsinterval, reservatieduur, max. covers, max. groepsgrootte en lead time instelbaar in de admin.
- Auditlog voor belangrijke beheeracties.

## Techniek

- PHP 8.1+
- PDO
- MySQL/MariaDB voor productie
- SQLite voor lokale ontwikkeling
- Vanilla JavaScript (geen build-stap)
- HTML5 drag-and-drop + pointer events
- Geen framework- of licentiekosten

## Mappen

```text
app/                    Core PHP (database, auth, reservatielogica, mail)
config/                 Lokale configuratie (config.php wordt niet gecommit)
database/               MySQL- en SQLite-schema
public/                  Webroot
  admin/                 Backoffice
  assets/                CSS + JavaScript
scripts/                 Setup- en beheerscripts
```

## Lokaal starten

Vereisten: PHP 8.1+ met `pdo_sqlite`.

```bash
php scripts/init_sqlite.php
php scripts/create_admin.php admin@de-pasto.be 'kies-een-sterk-wachtwoord' 'Warre'
php scripts/seed_demo.php
php -S 127.0.0.1:8080 -t public
```

Open daarna:

- Gast: `http://127.0.0.1:8080/`
- Beheer: `http://127.0.0.1:8080/admin/`

## Productie op MySQL / Combell

1. Maak een lege MySQL- of MariaDB-database aan.
2. Importeer `database/schema.mysql.sql`.
3. Kopieer `config/config.example.php` naar `config/config.php`.
4. Vul de databasegegevens en `app.base_url` in.
5. Zorg dat het domein/subdomein als document root naar de map `public/` wijst.
6. Maak een beheerder aan via CLI:

```bash
php scripts/create_admin.php admin@de-pasto.be 'kies-een-sterk-wachtwoord' 'Warre'
```

7. Zet in `config/config.php` eventueel e-mailmeldingen aan:

```php
'mail' => [
    'enabled' => true,
    'from_email' => 'reservaties@de-pasto.be',
    'from_name' => 'De Pasto',
    'notify_email' => 'reservaties@de-pasto.be',
],
```

> Als de hosting geen custom document root ondersteunt, zet de inhoud van `public/` in de webroot en pas de paden in de bootstrap aan. Een subdomein zoals `reserveer.de-pasto.be` is technisch het netst.

## Tafelplan

Het schema bevat **voorbeeldtafels** voor Binnen, Terras en Tuin zodat het systeem meteen bruikbaar en testbaar is. Dit is nog geen exacte opmeting van De Pasto. In de backoffice kan je:

1. een zone openen;
2. `Indeling aanpassen` kiezen;
3. tafels naar de juiste positie slepen;
4. met `+ Tafel` extra tafels toevoegen.

Wanneer de definitieve tafelnummering en het echte grondplan gekend zijn, kan de seed exact daarop worden afgestemd.

## Reserveringslogica

Online reservaties worden **niet automatisch aan een tafel toegewezen**. Dat is bewust: ze komen binnen als `Nieuw` in de inbox. Een medewerker beslist vervolgens zelf welke tafel het beste past en sleept de reservatie naar die tafel.

De publieke beschikbaarheid bewaakt wel het totaal aantal covers per overlappend tijdslot, zodat het systeem niet onbeperkt reservaties blijft aannemen terwijl de concrete tafelkeuze intern flexibel blijft.

## Volgende uitbreidingen

De codebasis is voorbereid om verder uit te bouwen. Logische volgende stappen zijn onder meer:

- tafelcombinaties (één reservatie over meerdere tafels);
- automatische suggestie van de beste tafel, zonder automatisch toe te wijzen;
- wachtlijst bij volle momenten;
- bevestigen/wijzigen/annuleren via persoonlijke link;
- e-mail via SMTP/API i.p.v. PHP `mail()`;
- WhatsApp/SMS-reminders;
- dashboard met no-show- en bezettingsstatistieken;
- export naar CSV/Excel;
- koppeling met het kassasysteem;
- rollen voor beheerder / shiftlead / medewerker;
- echte plattegrond als achtergrondlaag voor het tafelplan.

## Beveiliging

- Wachtwoorden via `password_hash()` / `password_verify()`.
- Session cookies zijn `HttpOnly` en `SameSite=Lax`.
- CSRF-bescherming op adminmutaties.
- PDO prepared statements.
- Publieke honeypot + rate limiting.
- `config/config.php` en lokale databases staan in `.gitignore` en horen niet in de publieke webroot.

---

**De Pasto** - warm in sfeer, duidelijk in organisatie.
