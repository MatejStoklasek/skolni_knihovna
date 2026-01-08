




-- Drop tables in correct order (respecting foreign key constraints)
DROP TABLE IF EXISTS pujceni;
DROP TABLE IF EXISTS kniha;
DROP TABLE IF EXISTS autor;
DROP TABLE IF EXISTS vydavatel;
DROP TABLE IF EXISTS zak_zakaznici;
DROP TABLE IF EXISTS ucitel_zamestnanec;

-- Create tables
CREATE TABLE IF NOT EXISTS zak_zakaznici
(
    zakID    INT      NOT NULL,
    jmeno    CHAR(50) NOT NULL,
    prijmeni CHAR(50) NOT NULL,
    trida    CHAR(3)  NOT NULL,
    PRIMARY KEY (zakID)
);

CREATE TABLE IF NOT EXISTS ucitel_zamestnanec
(
    zamestnanecID INT      NOT NULL,
    jmeno         CHAR(50) NOT NULL,
    prijmeni      CHAR(50) NOT NULL,
    kabinet       CHAR(50) NOT NULL,
    PRIMARY KEY (zamestnanecID)
);

CREATE TABLE IF NOT EXISTS vydavatel
(
    vydavatelID INT      NOT NULL,
    nazev       CHAR(50) NOT NULL,
    PRIMARY KEY (vydavatelID)
);

CREATE TABLE IF NOT EXISTS autor
(
    autorID  INT      NOT NULL,
    jmeno    CHAR(50) NOT NULL,
    prijmeni CHAR(50) NOT NULL,
    PRIMARY KEY (autorID)
);

CREATE TABLE IF NOT EXISTS kniha
(
    knihaID     INT NOT NULL,
    nazev       CHAR(50) NOT NULL,
    strany      INT NOT NULL,
    zanr        CHAR(50) NOT NULL,
    ks          INT NOT NULL,
    vydavatelID INT NOT NULL,
    autorID     INT NOT NULL,
    PRIMARY KEY (knihaID),
    FOREIGN KEY (vydavatelID) REFERENCES vydavatel (vydavatelID),
    FOREIGN KEY (autorID) REFERENCES autor (autorID)
);

CREATE TABLE IF NOT EXISTS pujceni
(
    pujceniID     INT  NOT NULL,
    delka_cas     DATE NOT NULL,
    zakID         INT  NOT NULL,
    zamestnanecID INT  NOT NULL,
    knihaID       INT  NOT NULL,
    PRIMARY KEY (pujceniID),
    FOREIGN KEY (zakID) REFERENCES zak_zakaznici (zakID),
    FOREIGN KEY (zamestnanecID) REFERENCES ucitel_zamestnanec (zamestnanecID),
    FOREIGN KEY (knihaID) REFERENCES kniha (knihaID)
);

-- Vložení dat do tabulky vydavatel
INSERT INTO vydavatel (vydavatelID, nazev)
VALUES (1, 'Albatros'),
       (2, 'Fragment'),
       (3, 'Mladá fronta'),
       (4, 'Grada'),
       (5, 'Academia'),
       (6, 'Paseka'),
       (7, 'Host'),
       (8, 'Argo'),
       (9, 'Československý spisovatel'),
       (10, 'Odeon'),
       (11, 'Triton'),
       (12, 'Portál');

-- Vložení dat do tabulky autor
INSERT INTO autor (autorID, jmeno, prijmeni)
VALUES (1, 'Karel', 'Čapek'),
       (2, 'Bohumil', 'Říha'),
       (3, 'J.K.', 'Rowling'),
       (4, 'Jules', 'Verne'),
       (5, 'Antoine', 'de Saint-Exupéry'),
       (6, 'Jaroslav', 'Foglar'),
       (7, 'Božena', 'Němcová'),
       (8, 'Alois', 'Jirásek'),
       (9, 'Jan', 'Drda'),
       (10, 'Ota', 'Pavel'),
       (11, 'Karel', 'Poláček'),
       (12, 'Arnošt', 'Lustig');

-- Vložení dat do tabulky kniha
INSERT INTO kniha (knihaID, nazev, strany, zanr, ks, vydavatelID, autorID)
VALUES (1, 'R.U.R.', 120, 'sci-fi', 3, 3, 1),
       (2, 'Honzíkova cesta', 200, 'dětská', 5, 1, 2),
       (3, 'Harry Potter', 350, 'fantasy', 4, 2, 3),
       (4, 'Cesta kolem světa', 280, 'dobrodružná', 2, 4, 4),
       (5, 'Malý princ', 96, 'pohádka', 6, 5, 5),
       (6, 'Chata v Jezerní kotlině', 240, 'dobrodružná', 3, 1, 6),
       (7, 'Babička', 180, 'próza', 4, 10, 7),
       (8, 'Psohlavci', 320, 'historická', 2, 9, 8),
       (9, 'Hrátky s čertem', 150, 'pohádka', 5, 9, 9),
       (10, 'Smrt krásných srnců', 160, 'próza', 3, 3, 10),
       (11, 'Bylo nás pět', 200, 'humoristická', 4, 9, 11),
       (12, 'Modlitba pro Kateřinu Horovitzovou', 140, 'próza', 2, 9, 12);

-- Vložení dat do tabulky zak_zakaznici
INSERT INTO zak_zakaznici (zakID, jmeno, prijmeni, trida)
VALUES (1, 'Jan', 'Novák', '5.A'),
       (2, 'Petra', 'Svobodová', '2.B'),
       (3, 'Martin', 'Dvořák', '8.C'),
       (4, 'Anna', 'Němcová', '1.A'),
       (5, 'Tomáš', 'Procházka', '4.D'),
       (6, 'Lucie', 'Málková', '9.B'),
       (7, 'David', 'Černý', '6.C'),
       (8, 'Karolína', 'Marková', '7.A'),
       (9, 'Jakub', 'Pospíšil', '4.D'),
       (10, 'Tereza', 'Křížová', '6.A'),
       (11, 'Filip', 'Kučera', '3.B'),
       (12, 'Barbora', 'Novotná', '1.C');

-- Vložení dat do tabulky ucitel_zamestnanec
INSERT INTO ucitel_zamestnanec (zamestnanecID, jmeno, prijmeni, kabinet)
VALUES (1, 'Eva', 'Horáková', 'Kabinet č. 101'),
       (2, 'Pavel', 'Král', 'Kabinet č. 102'),
       (3, 'Marie', 'Veselá', 'Knihovna'),
       (4, 'Jiří', 'Novotný', 'Kabinet č. 103'),
       (5, 'Jana', 'Malá', 'Kabinet č. 104'),
       (6, 'Petr', 'Svoboda', 'Kabinet č. 105'),
       (7, 'Lenka', 'Dvořáková', 'Knihovna'),
       (8, 'Michal', 'Černý', 'Kabinet č. 106'),
       (9, 'Hana', 'Procházková', 'Kabinet č. 107'),
       (10, 'Lukáš', 'Horák', 'Kabinet č. 108');

-- Vložení dat do tabulky pujceni
INSERT INTO pujceni (pujceniID, delka_cas, zakID, zamestnanecID, knihaID)
VALUES (1, '2025-01-15', 1, 3, 2),
       (2, '2025-01-20', 2, 3, 3),
       (3, '2025-01-18', 3, 3, 1),
       (4, '2025-01-25', 4, 3, 5),
       (5, '2025-01-22', 5, 3, 4),
       (6, '2025-01-16', 6, 7, 6),
       (7, '2025-01-21', 7, 3, 7),
       (8, '2025-01-19', 8, 7, 8),
       (9, '2025-01-23', 9, 3, 9),
       (10, '2025-01-17', 10, 7, 10),
       (11, '2025-01-24', 11, 3, 11),
       (12, '2025-01-20', 12, 7, 12);

SELECT jmeno, prijmeni, trida FROM zak_zakaznici WHERE zakID = 7;
SELECT jmeno, prijmeni, trida FROM zak_zakaznici ORDER BY trida;

SELECT * FROM pujceni;

SELECT nazev, strany, ks FROM kniha;

SELECT nazev, strany, ks, jmeno, prijmeni, delka_cas
FROM kniha
         JOIN pujceni ON pujceni.knihaID = kniha.knihaID
         JOIN zak_zakaznici ON pujceni.zakID = zak_zakaznici.zakID;

SELECT nazev, strany, jmeno, prijmeni
FROM kniha
         JOIN zak_zakaznici ON kniha.autorID = zak_zakaznici.zakID
WHERE ks > 2
ORDER BY strany DESC;


 