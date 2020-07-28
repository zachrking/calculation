BEGIN TRANSACTION;

CREATE TABLE IF NOT EXISTS "sy_Category" (
	"id"	        integer PRIMARY KEY AUTOINCREMENT,
	"code"	        varchar(30)  NOT NULL,
	"description"	varchar(255) DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS "sy_CategoryMargin" (
	"id"	        integer PRIMARY KEY AUTOINCREMENT,
	"category_id"	integer NOT NULL,
	"minimum"	    double  NOT NULL DEFAULT '0',
	"maximum"	    double  NOT NULL DEFAULT '0',
	"margin"	    double  NOT NULL DEFAULT '0',
    FOREIGN KEY(category_id) REFERENCES sy_Category(id)
);

CREATE TABLE IF NOT EXISTS "sy_Product" (
	"id"	        integer PRIMARY KEY AUTOINCREMENT,
	"category_id"	integer      NOT NULL,
	"description"	varchar(255) NOT NULL,
	"unit"	        varchar(15)  DEFAULT NULL,
	"price"	        double       NOT NULL DEFAULT '0',
	"supplier"	    varchar(255) DEFAULT NULL,
    FOREIGN KEY(category_id) REFERENCES sy_Category(id)
);

CREATE TABLE IF NOT EXISTS "sy_CalculationState" (
	"id"	        integer PRIMARY KEY AUTOINCREMENT,
	"code"	        varchar(30)  NOT NULL,
	"description"	varchar(255) DEFAULT NULL,
	"editable"	    tinyint(1)   DEFAULT '1',
	"color"	        varchar(10)  DEFAULT '#000000'
);

CREATE TABLE IF NOT EXISTS "sy_Calculation" (
	"id"	        integer PRIMARY KEY AUTOINCREMENT,
	"customer"	    varchar(255) NOT NULL,
	"description"	varchar(255) NOT NULL,
	"date"	        date         DEFAULT NULL,
	"state_id"	    integer      NOT NULL,
	"userMargin"	double       NOT NULL DEFAULT '0',
	"globalMargin"	double       NOT NULL DEFAULT '0',
	"itemsTotal"	double       NOT NULL DEFAULT '0',
	"overallTotal"	double       NOT NULL DEFAULT '0',
	"created_at"	datetime     DEFAULT NULL,
	"created_by"	varchar(255) DEFAULT NULL,
	"updated_at"	datetime     DEFAULT NULL,
	"updated_by"	varchar(255) DEFAULT NULL,
    FOREIGN KEY(state_id) REFERENCES sy_CalculationState(id)
);

CREATE TABLE IF NOT EXISTS "sy_CalculationGroup" (
	"id"	            integer PRIMARY KEY AUTOINCREMENT,
	"calculation_id"	integer     NOT NULL,
	"category_id"	    integer     NOT NULL,
	"code"	            varchar(30) NOT NULL,
	"margin"	        double      NOT NULL DEFAULT '0',
	"amount"	        double      NOT NULL DEFAULT '0',
    FOREIGN KEY(calculation_id) REFERENCES sy_Calculation(id),
    FOREIGN KEY(category_id) REFERENCES sy_Category(id)
);

CREATE TABLE IF NOT EXISTS "sy_CalculationItem" (
	"id"	        integer PRIMARY KEY AUTOINCREMENT,
	"group_id"	    integer      NOT NULL,
	"description"   varchar(255) NOT NULL,
	"unit"	        varchar(15)  DEFAULT NULL,
	"price"	        double       NOT NULL DEFAULT '0',
	"quantity"	    double       NOT NULL DEFAULT '0',
    FOREIGN KEY(group_id) REFERENCES sy_CalculationGroup(id)
);

CREATE TABLE IF NOT EXISTS "sy_GlobalMargin" (
	"id"	        integer PRIMARY KEY AUTOINCREMENT,
	"minimum"       double NOT NULL DEFAULT '0',
	"maximum"	    double NOT NULL DEFAULT '0',
	"margin"	    double NOT NULL DEFAULT '0'
);

CREATE TABLE IF NOT EXISTS "sy_Customer" (
  "id"          integer PRIMARY KEY AUTOINCREMENT,
  "company"     varchar(255) DEFAULT NULL,
  "firstName"   varchar(255) DEFAULT NULL,
  "lastName"    varchar(255) DEFAULT NULL,
  "title"       varchar(50)  DEFAULT NULL,
  "address"     varchar(255) DEFAULT NULL,
  "zipCode"     varchar(10)  DEFAULT NULL,
  "city"        varchar(255) DEFAULT NULL,
  "country"     varchar(100) DEFAULT NULL,
  "email"       varchar(100) DEFAULT NULL,
  "webSite"     varchar(100) DEFAULT NULL,
  "birthday"    date         DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS "sy_Property" (
	"id"	        integer PRIMARY KEY AUTOINCREMENT,
	"name"	        varchar(50)  NOT NULL,
	"value"	        varchar(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS "sy_User" (
	"id"	        integer PRIMARY KEY AUTOINCREMENT,
	"email"	        varchar(180) NOT NULL,
	"username"	    varchar(180) NOT NULL,
	"password"	    varchar(255) DEFAULT NULL,
	"image_name"	varchar(255) DEFAULT NULL,
	"role"	        varchar(25)  DEFAULT NULL,
	"rights"	    varchar(50)  DEFAULT NULL,
	"overwrite"	    tinyint(1)   DEFAULT '0',
	"enabled"	    tinyint(1)   DEFAULT '1',
	"last_login"	datetime     DEFAULT NULL,
	"updated_at"	datetime     DEFAULT NULL,
	"selector" 		varchar(20)  DEFAULT NULL,
  	"hashed_token"  varchar(100) DEFAULT NULL,
  	"requested_at" 	datetime     DEFAULT NULL,
  	"expires_at" 	datetime     DEFAULT NULL,
  	"verified" 		tinyint(1)   DEFAULT '0'
);

INSERT INTO "sy_User"
(
	'id', 'email', 'username', 'enabled', 'password', 'role'
)
VALUES
(
	1, 'ROLE_SUPER_ADMIN@test.com', 'ROLE_SUPER_ADMIN', 1, 'ROLE_SUPER_ADMIN', 'ROLE_SUPER_ADMIN'
);

INSERT INTO "sy_User"
(
	'id', 'email', 'username', 'enabled', 'password', 'role'
)
VALUES
(
	2, 'ROLE_ADMIN@test.com', 'ROLE_ADMIN', 1, 'ROLE_ADMIN', 'ROLE_ADMIN'
);

INSERT INTO "sy_User"
(
	'id', 'email', 'username', 'enabled', 'password'
)
VALUES
(
	3, 'ROLE_USER@test.com', 'ROLE_USER', 1, 'ROLE_USER'
);

INSERT INTO "sy_User"
(
	'id', 'email', 'username', 'enabled', 'password'
)
VALUES
(
	4, 'ROLE_DISABLED@test.com', 'ROLE_DISABLED', 0, 'ROLE_DISABLED'

);

COMMIT;
