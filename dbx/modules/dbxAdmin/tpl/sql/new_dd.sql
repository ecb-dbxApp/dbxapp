CREATE TABLE `{table}` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `create_date` datetime DEFAULT NULL,
  `create_uid` int(11) NOT NULL DEFAULT 0,
  `update_date` datetime DEFAULT NULL,
  `update_uid` int(11) NOT NULL DEFAULT 0,
  `owner`  int(11) NOT NULL DEFAULT 0,
  `rel_id` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `rel_id` (`rel_id`,`id`)
) ENGINE=MyISAM AUTO_INCREMENT=0 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
