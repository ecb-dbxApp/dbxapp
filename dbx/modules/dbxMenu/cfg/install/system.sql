
SET FOREIGN_KEY_CHECKS=0;


DROP TABLE IF EXISTS `dbx_session`;
CREATE TABLE `dbx_session` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `create_date` datetime DEFAULT NULL,
  `create_uid` int(11) NOT NULL DEFAULT 0,
  `update_date` datetime DEFAULT NULL,
  `update_uid` int(11) NOT NULL DEFAULT 0,
  `owner` int(11) NOT NULL DEFAULT 0,
  `sessid` char(32) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `userid` int(11) NOT NULL DEFAULT 0,
  `ip` char(32) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `host` char(32) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `lastaction` datetime NOT NULL,
  `design` char(32) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `language` char(3) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `request_counter` int(11) NOT NULL DEFAULT 0,
  `request_last` char(254) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `request_current` char(254) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `counter_id` int(11) NOT NULL DEFAULT 0,
  `mobile` int(1) NOT NULL DEFAULT 0,
  `robot` int(1) NOT NULL DEFAULT 0,
  `name` char(32) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `ver` char(32) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `os` char(32) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `width` int(6) NOT NULL DEFAULT 0,
  `height` int(6) NOT NULL DEFAULT 0,
  `cookie` int(1) NOT NULL DEFAULT 0,
  `settings` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sessid` (`sessid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Data for Table `dbx_session`
--

/*!40000 ALTER TABLE `dbx_session` DISABLE KEYS */;
/*!40000 ALTER TABLE `dbx_session` ENABLE KEYS */;

SET FOREIGN_KEY_CHECKS=1;
-- EOB

