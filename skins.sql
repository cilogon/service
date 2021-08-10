/**
 * Run this as user 'root' as follows:
 *     mysql -u root -p < skins.sql
 * This assumes that the user 'cilogon' and database 'ciloa2' have been
 * created. This SQL needs to be run just once to create the skins table.
 */

GRANT ALL PRIVILEGES on ciloa2.skins to 'cilogon'@'localhost' WITH GRANT OPTION;
COMMIT;

CREATE TABLE IF NOT EXISTS ciloa2.skins (
    name VARCHAR(255) PRIMARY KEY,
    config TEXT,
    css TEXT
);
