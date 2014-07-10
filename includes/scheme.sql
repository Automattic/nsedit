PRAGMA foreign_keys = 1;

CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    emailaddress VARCHAR UNIQUE NOT NULL,
    password VARCHAR NOT NULL,
    isadmin BOOLEAN DEFAULT FALSE);

CREATE TABLE zones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    zone VARCHAR NOT NULL,
    owner INTEGER NOT NULL,
    UNIQUE(zone),
    FOREIGN KEY(owner) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE );
