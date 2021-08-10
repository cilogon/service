/**
 * Run this as user 'root' as follows:
 *     mysql -u root -p < bypass.sql
 * This assumes that the user 'cilogon' and database 'ciloa2' have been
 * created. This SQL needs to be run just once to create the bypass table.
 */

GRANT ALL PRIVILEGES on ciloa2.bypass to 'cilogon'@'localhost' WITH GRANT OPTION;
COMMIT;

CREATE TABLE IF NOT EXISTS ciloa2.bypass (
    type ENUM('allow', 'idp', 'skin') NOT NULL DEFAULT 'allow',
    regex VARCHAR(255) NOT NULL DEFAULT '%%',
    value VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY(type,regex)
);
