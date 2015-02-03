CREATE TABLE Purchases (
  id int(10) NOT NULL AUTO_INCREMENT,
  book_id int(10) NOT NULL,
  date datetime DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE USER (
  id int(10) NOT NULL AUTO_INCREMENT,
  password varchar(32) DEFAULT NULL,
  recovery_hash varchar(50) DEFAULT NULL,
  date datetime DEFAULT NULL,
  email varchar(50) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX email (email)
);

CREATE TABLE SESSIONS (
  id int(10) NOT NULL AUTO_INCREMENT,
  name varchar(32) DEFAULT NULL,
  user_id int(10) DEFAULT NULL,
  PRIMARY KEY (id)
);