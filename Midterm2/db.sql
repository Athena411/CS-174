CREATE TABLE users (
email VARCHAR(32) NOT NULL UNIQUE,
username VARCHAR(32) NOT NULL UNIQUE,
password VARCHAR(32) NOT NULL,
passwordSalt VARCHAR(32) NOT NULL
);


CREATE TABLE userInputs(
    Name    VARCHAR(256) not NULL,
    Content   VARCHAR(256),
    email VARCHAR(32) NOT NULL
);