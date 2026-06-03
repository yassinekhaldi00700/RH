-- ============================================================
-- Script SQL : Creation table vacataires_emadu
-- Base de données : emadu
-- Source : VACATAIRES EMADU.csv + vacataire1.csv (BioStar groupe 1007)
-- NB : Les personnes AMBIGUES (2 IDs BioStar) sont inserees 2 fois
-- NB : Les personnes NON TROUVEES sont inserees avec id_biostar NULL
-- ============================================================

CREATE DATABASE IF NOT EXISTS `emadu`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `emadu`;

DROP TABLE IF EXISTS `vacataires_emadu`;

CREATE TABLE `vacataires_emadu` (
  `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `id_biostar`  INT UNSIGNED     NULL COMMENT 'NULL si non trouve dans BioStar',
  `nom`         VARCHAR(100)     NOT NULL,
  `prenom`      VARCHAR(150)     NOT NULL,
  `nm_biostar`  VARCHAR(200)     NULL    COMMENT 'Nom tel qu enregistre dans BioStar',
  `groupe`      VARCHAR(50)      NOT NULL DEFAULT 'EMADU',
  `remarque`    VARCHAR(255)     NULL,
  `created_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_id_biostar` (`id_biostar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INSERTIONS
-- ============================================================
INSERT INTO `vacataires_emadu` (`id_biostar`, `nom`, `prenom`, `nm_biostar`, `groupe`, `remarque`) VALUES
(1119,  'AFAJJAY',           'NAIMA',          'FAJJAY Naima',                  'EMADU', 'Orthographe BioStar: FAJJAY'),
(9600,  'AKAZAF',            'MOHAMMED',       'Akazaf Mohammed',                'EMADU', NULL),
(3589,  'AKKI',              'MOURAD',         'Akki Mourad',                    'EMADU', NULL),
(7690,  'ALAMI AROUSSI',     'ZAKARIAE',       'ALAMI AROUSSI Zakariae',         'EMADU', NULL),
(3528,  'AMRANI',            'MUSTAPHA',       'Amrani Mustapha',                'EMADU', NULL),
(7687,  'AOUNI',             'LHAJ MOUSSA',    'LHAJ AOUNI Moussa',              'EMADU', NULL),
(2793,  'BASSET',            'ABDESLAM',       'BASSET Abdessalam',              'EMADU', NULL),
(1042,  'BEKKALI',           'RACHID',         'Bekkali Rachid',                 'EMADU', NULL),
(3529,  'BENADDIA',          'SOUMAYA',        'Benaddia Soumaya',               'EMADU', NULL),
(7895,  'BENBRAHIM',         'RIHAB',          'BENBRAHIM rihab',                'EMADU', NULL),
(10312, 'BENBRAHIM',         'MOHAMMED',       'BENBRAHIM Mohamed',              'EMADU', NULL),
(4721,  'BENLEMLIH',         'JASSIM',         'Benlemlih Jassim',               'EMADU', NULL),
(11847, 'BENMANSOUR',        'WALID',          'Walid Benmansour',               'EMADU', NULL),
(3896,  'BENNANI',           'MAHMOUD',        'Bennani Mahmoud',                'EMADU', NULL),
(3534,  'BENNANI',           'DRISS',          'Bennani Driss',                  'EMADU', NULL),
(9596,  'BENOMAR',           'MANAL',          'Benomar Manal',                  'EMADU', NULL),
(9599,  'BENZAKOUR',         'ABDELMAJID',     'Benzakour Majid',                'EMADU', 'A verifier: Majid = Abdelmajid ?'),
(1349,  'BENZAKOUR',         'YAHYA',          'Benzakour Yahya',                'EMADU', NULL),
(4616,  'BLELLETTA',         'KAMAL',          'Beleletta Kamal',                'EMADU', 'Orthographe BioStar: BELELETTA'),
(NULL,  'BOUASSRIA',         'IKRAM',          NULL,                             'EMADU', 'NON TROUVE dans BioStar groupe 1007'),
(1364,  'BOURHIM',           'ABDELALI',       'Bourhim Abdelali',               'EMADU', NULL),
(7871,  'BOUZAYAR',          'MARIA',          'Bouzayar Maria',                 'EMADU', NULL),
(3536,  'CHAKROUNE',         'SAMIRA',         'Chakroune Samira',               'EMADU', NULL),
(1310,  'CHARAI',            'ZINEB',          'Charai Zineb',                   'EMADU', NULL),
(9670,  'CHEKKOURY IDRISSI', 'DIANA',          'CHEKKOURY Idrissi Diana',        'EMADU', NULL),
-- AMBIGU: DANI HICHAM -> 2 entrees BioStar (IDs 7692 et 4830)
(7692,  'DANI',              'HICHAM',         'DANI Hicham',                    'EMADU', 'AMBIGU id_biostar 7692 - a verifier'),
(4830,  'DANI',              'HICHAM',         'Dani Hicham',                    'EMADU', 'AMBIGU id_biostar 4830 - a verifier'),
(9861,  'ED DARIY',          'YASMINA',        'EDDARIY Yasmina',                'EMADU', NULL),
(8135,  'EL AZAMI',          'MOHAMMED',       'EL AZAMI Mohammed',              'EMADU', NULL),
(2605,  'EL BEQQAL',         'SABAH',          'El Beqqal Sabah',                'EMADU', NULL),
(12164, 'EL HAJRAOUI',       'RHITA',          'Rhita El Hajraoui',              'EMADU', NULL),
(12744, 'EL HASSOUNI',       'FIDAE',          'El Hassouni Fidae',              'EMADU', NULL),
(7422,  'EL HOUARI',         'ABDALLAH',       'EL Houari Abdallah',             'EMADU', NULL),
(1172,  'EL KHAYATI',        'MOUMINA',        'El khayati Moumina',             'EMADU', NULL),
(2506,  'EL MOUSSAOUI',      'ABDELAZIZ',      'El Moussaoui Abdelaziz',         'EMADU', NULL),
(2544,  'EL WARDI',          'MOHAMMED',       'El wardi Mohammed',              'EMADU', NULL),
(3644,  'EL-AMRANI',         'HASSAN',         'El amrani Hassan',               'EMADU', NULL),
(7420,  'ERAJAI',            'NAJIA',          'ERAJAI Najia',                   'EMADU', NULL),
(NULL,  'FILALI ADIB',       'JAAFAR',         NULL,                             'EMADU', 'NON TROUVE dans BioStar groupe 1007'),
(2799,  'GARZIAD',           'MOUAD',          'Garziad Mouad',                  'EMADU', NULL),
(1093,  'GORLOVA',           'VERONIKA',       'Gorlova Veronika',               'EMADU', NULL),
(8117,  'GUENNOUN',          'BTISSAM',        'Guennoun Btissam',               'EMADU', NULL),
(1210,  'HABIB',             'AZZEDDINE',      'Habib Azzeddine',                'EMADU', NULL),
(4612,  'HADDAR',            'DRISS',          'Alhaddar Driss',                 'EMADU', 'Orthographe BioStar: ALHADDAR'),
(12758, 'HAMDAOUI',          'SAID',           'Hamdaoui Said',                  'EMADU', NULL),
(9598,  'HARROUD',           'TARIK',          'Harroud Tarek',                  'EMADU', NULL),
(7334,  'HASSOUNI',          'OMAR',           'Hassouni Omar',                  'EMADU', NULL),
(11844, 'HOUNAIN',           'ABDELMALIK',     'Abdelmalik Hounain',             'EMADU', NULL),
(8190,  'IAALOU',            'HAMZA',          'IAALOU Hamza',                   'EMADU', NULL),
(4832,  'IBRAHIMI OUAZZANI', 'MOHAMMED',       'IBRAHIMI OUAZZANI Mohammed',     'EMADU', NULL),
(2452,  'ICHMETOVA',         'GOULNARA',       'Ichmetova Goulnara',             'EMADU', NULL),
(6234,  'IDRISSI KAITOUNI',  'AHMED',          'Idrissi Kaitouni Ahmed',         'EMADU', NULL),
(9847,  'IGAMANE',           'SAADEDDINE',     'IGAMANE Saadeddine',             'EMADU', NULL),
(3537,  'JELLOUL',           'EL HASSANE',     'Jelloul El hassane',             'EMADU', NULL),
(2649,  'KADRI',             'ANAS',           'Kadri Anas',                     'EMADU', NULL),
(12763, 'KIM',               'JISUN',          'JISUN Kim',                      'EMADU', NULL),
(12710, 'LOUKILI',           'MOHAMMED AMINE', 'Loukili Mohammed Amine',         'EMADU', NULL),
(11848, 'MARBOUH',           'CHAYMAE',        'Marbouh Chaymae',                'EMADU', NULL),
(7456,  'MEKOUAR',           'CHAKIB',         'Mekouar Chakib',                 'EMADU', NULL),
(12319, 'METTAR',            'MOHAMMED',       'Mettar Mohammed',                'EMADU', NULL),
(1223,  'MJIDOU',            'AHMED',          'Mjidou Ahmed',                   'EMADU', NULL),
(11883, 'MOUNJIB',           'OUMAYMA',        'Mounjid Oumayma',                'EMADU', NULL),
(12479, 'MOUSTABCHIR',       'HASSANE',        'Moustabchir Hassane',            'EMADU', NULL),
(11851, 'MRINI',             'BASMA',          'Mrini Basma',                    'EMADU', NULL),
(11843, 'MUKHAMETCHINA',     'ILMIRA',         'Moukhametchina Ilmira',          'EMADU', NULL),
-- AMBIGU: NIRHECHE ADIL -> 2 entrees BioStar (IDs 7650 et 2893)
(7650,  'NIRHECHE',          'ADIL',           'NIRHECHE Adil',                  'EMADU', 'AMBIGU id_biostar 7650 - a verifier'),
(2893,  'NIRHECHE',          'ADIL',           'Nirheche Adil',                  'EMADU', 'AMBIGU id_biostar 2893 - a verifier'),
(1034,  'OUEDGHIRI SAIDI',   'MARIAM',         'OUEDEGHIRI SAIDI MARIAM',        'EMADU', NULL),
(6236,  'OURFI',             'JAMAL',          'Ourfi Jamal',                    'EMADU', NULL),
(2543,  'SAADI',             'KARIM',          'Saadi Karim',                    'EMADU', NULL),
(1209,  'SABOUNY',           'ABDESSAMAD',     'Sabouny Abdessamed',             'EMADU', NULL),
(1162,  'SAFOUANE',          'ABLA',           'Safouane Abla',                  'EMADU', NULL),
(12709, 'SAIDI',             'EL MAHDI',       'Saidi El Mehdi',                 'EMADU', NULL),
-- AMBIGU: TALEB MUSTAPHA -> 2 entrees BioStar (IDs 5987 et 12326)
(5987,  'TALEB',             'MUSTAPHA',       'Taleb Mustapha',                 'EMADU', 'AMBIGU id_biostar 5987 - a verifier'),
(12326, 'TALEB',             'MUSTAPHA',       'Taleb Mustapha',                 'EMADU', 'AMBIGU id_biostar 12326 - a verifier'),
(1194,  'TAZI',              'HICHAM',         'Tazi Hicham',                    'EMADU', NULL),
(1219,  'TAZI',              'NABIL',          'Tazi Nabil',                     'EMADU', NULL),
(1226,  'TRAIBI',            'TOUIBA',         'Traibi Touaiba',                 'EMADU', NULL),
(3527,  'VIEIRA',            'SONIA',          'Vieira Sonia',                   'EMADU', NULL),
(3538,  'ZERHOUNI',          'HAMZA',          'Zerhouni Hamza',                 'EMADU', NULL),
(7727,  'ZOUITEN',           'SALMA',          'Zouiten Salma',                  'EMADU', NULL);

-- ============================================================
-- Verification
-- ============================================================
SELECT COUNT(*) AS total_inseres FROM `vacataires_emadu`;
SELECT * FROM `vacataires_emadu` WHERE `id_biostar` IS NULL;
SELECT * FROM `vacataires_emadu` WHERE `remarque` LIKE '%AMBIGU%';
