DROP DATABASE IF EXISTS `cbng_editdb_master`;
CREATE DATABASE `cbng_editdb_master`;
USE `cbng_editdb_master`;

GRANT SELECT
ON `cbng_editdb_master`.*
TO 'cbng_editdb_slv'@'%'
IDENTIFIED BY 'cbng-editdb-slave';

CREATE TABLE `editset` (
	`edittype`                     VARCHAR(32)    NOT NULL DEFAULT 'change',
	`editid`                       INTEGER        NOT NULL,
	`comment`                      VARBINARY(255) NOT NULL,
	`user`                         VARBINARY(255) NOT NULL,
	`user_edit_count`              INTEGER        NOT NULL,
	`user_distinct_pages`          INTEGER        NOT NULL,
	`user_warns`                   INTEGER        NOT NULL,
	`prev_user`                    VARBINARY(255) NOT NULL,
	`user_reg_time`                DATETIME       NOT NULL,
	`common_page_made_time`        DATETIME       NOT NULL,
	`common_title`                 VARBINARY(255) NOT NULL,
	`common_namespace`             VARCHAR(64)    NOT NULL,
	`common_creator`               VARBINARY(255) NOT NULL,
	`common_num_recent_edits`      INTEGER        NOT NULL,
	`common_num_recent_reversions` INTEGER        NOT NULL,
	`current_minor`                TINYINT(1)     NOT NULL DEFAULT 0,
	`current_timestamp`            DATETIME       NOT NULL,
	`current_text`                 MEDIUMBLOB     NOT NULL,
	`previous_timestamp`           DATETIME       NOT NULL,
	`previous_text`                MEDIUMBLOB     NOT NULL,
	`isvandalism`                  TINYINT(1)     NOT NULL,
	`isactive`                     TINYINT(1)     NOT NULL DEFAULT 0,
	`updated`                      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	`source`                       VARCHAR(128)       NULL,
	`reviewers`                    INTEGER            NULL,
	`reviewers_agreeing`           INTEGER            NULL,
	
	PRIMARY KEY (`editid`),
	INDEX USING BTREE (`updated`)
)
ENGINE=InnoDB
ROW_FORMAT=COMPRESSED 
KEY_BLOCK_SIZE=4;

CREATE TABLE `lastupdated` (
	`lastupdated` TIMESTAMP NOT NULL
);

CREATE TABLE `dumps` (
	`id` INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`time` TIMESTAMP NOT NULL
);

INSERT INTO `lastupdated` (`lastupdated`) VALUES (CURRENT_TIMESTAMP);

DELIMITER |

CREATE TRIGGER `update` AFTER UPDATE ON `editset`
FOR EACH ROW BEGIN
	UPDATE `lastupdated` SET `lastupdated` = NEW.`updated`;
END;
|

CREATE TRIGGER `insert` AFTER INSERT ON `editset`
FOR EACH ROW BEGIN
	UPDATE `lastupdated` SET `lastupdated` = NEW.`updated`;
END;
|
