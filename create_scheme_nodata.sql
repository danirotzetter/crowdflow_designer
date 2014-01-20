-- --------------------------------------------------------
-- Host:                         us-cdbr-east-03.cleardb.com
-- Server version:               5.5.34-log - MySQL Community Server (GPL)
-- Server OS:                    Linux
-- HeidiSQL version:             7.0.0.4053
-- Date/time:                    2014-01-19 17:43:49
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET FOREIGN_KEY_CHECKS=0 */;

-- Dumping structure for table heroku_81f3bf268364a4c.datasource
DROP TABLE IF EXISTS `datasource`;
CREATE TABLE IF NOT EXISTS `datasource` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) DEFAULT '0',
  `data` text,
  `datasource_type_id` int(11) DEFAULT NULL,
  `output_determined` int(11) DEFAULT '1',
  `output_media_type_id` int(11) DEFAULT '0',
  `output_ordered` int(11) DEFAULT '1',
  `pos_x` int(10) DEFAULT '0',
  `pos_y` int(10) DEFAULT '0',
  `workspace_id` int(10) DEFAULT NULL,
  `description` text,
  `platform_data` text,
  `items_count` int(10) DEFAULT '10',
  PRIMARY KEY (`id`),
  KEY `FK_input_workspace` (`workspace_id`),
  CONSTRAINT `FK_datasource_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspace` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Data exporting was unselected.


-- Dumping structure for table heroku_81f3bf268364a4c.datasource_task
DROP TABLE IF EXISTS `datasource_task`;
CREATE TABLE IF NOT EXISTS `datasource_task` (
  `datasource_id` int(10) NOT NULL DEFAULT '0',
  `task_id` int(10) NOT NULL DEFAULT '0',
  `workspace_id` int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`datasource_id`,`task_id`,`workspace_id`),
  KEY `FK_splitter_task_task` (`task_id`),
  KEY `FK_splitter_task_workspace` (`workspace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

-- Data exporting was unselected.


-- Dumping structure for table heroku_81f3bf268364a4c.macrotask
DROP TABLE IF EXISTS `macrotask`;
CREATE TABLE IF NOT EXISTS `macrotask` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) DEFAULT '0',
  `description` text,
  `date_created` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int(10) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `FK__user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

-- Data exporting was unselected.


-- Dumping structure for table heroku_81f3bf268364a4c.merger
DROP TABLE IF EXISTS `merger`;
CREATE TABLE IF NOT EXISTS `merger` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `description` text,
  `parameters` text,
  `platform_data` text,
  `merger_type_id` int(50) DEFAULT NULL,
  `data` text,
  `pos_x` int(10) DEFAULT '0',
  `pos_y` int(10) DEFAULT '0',
  `input_queue` text,
  `name` varchar(50) DEFAULT NULL,
  `workspace_id` int(10) DEFAULT NULL,
  `processed_flowitems` text,
  PRIMARY KEY (`id`),
  KEY `FK_merger_workspace` (`workspace_id`),
  CONSTRAINT `FK_merger_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspace` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Data exporting was unselected.


-- Dumping structure for table heroku_81f3bf268364a4c.merger_merger
DROP TABLE IF EXISTS `merger_merger`;
CREATE TABLE IF NOT EXISTS `merger_merger` (
  `source_merger_id` int(10) NOT NULL DEFAULT '0',
  `target_merger_id` int(10) NOT NULL DEFAULT '0',
  `workspace_id` int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`source_merger_id`,`target_merger_id`,`workspace_id`),
  KEY `FK__task_2` (`target_merger_id`),
  KEY `FK_task_task_workspace` (`workspace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

-- Data exporting was unselected.


-- Dumping structure for table heroku_81f3bf268364a4c.merger_task
DROP TABLE IF EXISTS `merger_task`;
CREATE TABLE IF NOT EXISTS `merger_task` (
  `merger_id` int(10) NOT NULL DEFAULT '0',
  `task_id` int(10) NOT NULL DEFAULT '0',
  `workspace_id` int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`merger_id`,`task_id`,`workspace_id`),
  KEY `FK_merger_task_task` (`task_id`),
  KEY `FK_merger_task_workspace` (`workspace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Data exporting was unselected.


-- Dumping structure for table heroku_81f3bf268364a4c.merger_workspace
DROP TABLE IF EXISTS `merger_workspace`;
CREATE TABLE IF NOT EXISTS `merger_workspace` (
  `merger_id` int(10) NOT NULL DEFAULT '0',
  `workspace_id` int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`merger_id`,`workspace_id`),
  KEY `FK_task_workspace_workspace` (`workspace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

-- Data exporting was unselected.


-- Dumping structure for table heroku_81f3bf268364a4c.postprocessor
DROP TABLE IF EXISTS `postprocessor`;
CREATE TABLE IF NOT EXISTS `postprocessor` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) DEFAULT NULL,
  `postprocessor_type_id` int(10) DEFAULT NULL,
  `workspace_id` int(10) DEFAULT NULL,
  `pos_x` int(10) DEFAULT '0',
  `pos_y` int(10) DEFAULT '0',
  `description` text,
  `date_created` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `validation_type_id` int(10) DEFAULT NULL,
  `parameters` text,
  `platform_data` text,
  `input_queue` text,
  `processed_flowitems` text,
  PRIMARY KEY (`id`),
  KEY `FK_postprocessor_task` (`postprocessor_type_id`),
  KEY `FK_postprocessor_workspace` (`workspace_id`),
  CONSTRAINT `FK_postprocessor_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspace` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Data exporting was unselected.


-- Dumping structure for table heroku_81f3bf268364a4c.postprocessor_merger
DROP TABLE IF EXISTS `postprocessor_merger`;
CREATE TABLE IF NOT EXISTS `postprocessor_merger` (
  `postprocessor_id` int(10) NOT NULL DEFAULT '0',
  `merger_id` int(10) NOT NULL DEFAULT '0',
  `workspace_id` int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`postprocessor_id`,`merger_id`,`workspace_id`),
  KEY `FK_merger_task_task` (`merger_id`),
  KEY `FK_merger_task_workspace` (`workspace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

-- Data exporting was unselected.


-- Dumping structure for table heroku_81f3bf268364a4c.postprocessor_splitter
DROP TABLE IF EXISTS `postprocessor_splitter`;
CREATE TABLE IF NOT EXISTS `postprocessor_splitter` (
  `postprocessor_id` int(10) NOT NULL DEFAULT '0',
  `splitter_id` int(10) NOT NULL DEFAULT '0',
  `workspace_id` int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`postprocessor_id`,`splitter_id`,`workspace_id`),
  KEY `FK_merger_task_task` (`splitter_id`),
  KEY `FK_merger_task_workspace` (`workspace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

-- Data exporting was unselected.


-- Dumping structure for table heroku_81f3bf268364a4c.postprocessor_task
DROP TABLE IF EXISTS `postprocessor_task`;
CREATE TABLE IF NOT EXISTS `postprocessor_task` (
  `postprocessor_id` int(10) NOT NULL DEFAULT '0',
  `task_id` int(10) NOT NULL DEFAULT '0',
  `workspace_id` int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`postprocessor_id`,`task_id`,`workspace_id`),
  KEY `FK_merger_task_task` (`task_id`),
  KEY `FK_merger_task_workspace` (`workspace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

-- Data exporting was unselected.


-- Dumping structure for table heroku_81f3bf268364a4c.postprocessor_workspace
DROP TABLE IF EXISTS `postprocessor_workspace`;
CREATE TABLE IF NOT EXISTS `postprocessor_workspace` (
  `postprocessor_id` int(10) NOT NULL DEFAULT '0',
  `workspace_id` int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`postprocessor_id`,`workspace_id`),
  KEY `FK_task_workspace_workspace` (`workspace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

-- Data exporting was unselected.


-- Dumping structure for table heroku_81f3bf268364a4c.splitter
DROP TABLE IF EXISTS `splitter`;
CREATE TABLE IF NOT EXISTS `splitter` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `description` text,
  `pos_x` int(10) DEFAULT '0',
  `pos_y` int(10) DEFAULT '0',
  `workspace_id` int(10) DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  `platform_data` text,
  `input_queue` text,
  `parameters` text,
  `processed_flowitems` text,
  PRIMARY KEY (`id`),
  KEY `FK_splitter_workspace` (`workspace_id`),
  CONSTRAINT `FK_splitter_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspace` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Data exporting was unselected.


-- Dumping structure for table heroku_81f3bf268364a4c.splitter_merger
DROP TABLE IF EXISTS `splitter_merger`;
CREATE TABLE IF NOT EXISTS `splitter_merger` (
  `splitter_id` int(10) NOT NULL DEFAULT '0',
  `merger_id` int(10) NOT NULL DEFAULT '0',
  `workspace_id` int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`splitter_id`,`merger_id`,`workspace_id`),
  KEY `FK__task_2` (`merger_id`),
  KEY `FK_task_task_workspace` (`workspace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

-- Data exporting was unselected.


-- Dumping structure for table heroku_81f3bf268364a4c.splitter_task
DROP TABLE IF EXISTS `splitter_task`;
CREATE TABLE IF NOT EXISTS `splitter_task` (
  `splitter_id` int(10) NOT NULL DEFAULT '0',
  `task_id` int(10) NOT NULL DEFAULT '0',
  `workspace_id` int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`splitter_id`,`task_id`,`workspace_id`),
  KEY `FK_splitter_task_task` (`task_id`),
  KEY `FK_splitter_task_workspace` (`workspace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Data exporting was unselected.


-- Dumping structure for table heroku_81f3bf268364a4c.splitter_workspace
DROP TABLE IF EXISTS `splitter_workspace`;
CREATE TABLE IF NOT EXISTS `splitter_workspace` (
  `splitter_id` int(10) NOT NULL DEFAULT '0',
  `workspace_id` int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`splitter_id`,`workspace_id`),
  KEY `FK_task_workspace_workspace` (`workspace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

-- Data exporting was unselected.


-- Dumping structure for table heroku_81f3bf268364a4c.task
DROP TABLE IF EXISTS `task`;
CREATE TABLE IF NOT EXISTS `task` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) DEFAULT NULL,
  `user_id` int(10) DEFAULT NULL,
  `parameters` text,
  `date_created` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `pos_x` int(10) DEFAULT '0',
  `pos_y` int(10) DEFAULT '0',
  `workspace_id` int(10) DEFAULT NULL,
  `task_type_id` int(10) DEFAULT '1',
  `output_media_type_id` int(2) DEFAULT '1',
  `output_determined` int(1) DEFAULT '0',
  `output_mapping_type_id` int(2) DEFAULT '2',
  `output_ordered` int(1) DEFAULT '0',
  `description` text,
  `platform_data` text,
  `input_queue` text,
  `processed_flowitems` text,
  `data` text,
  PRIMARY KEY (`id`),
  KEY `FK_task_user` (`user_id`),
  KEY `FK_task_workspace` (`workspace_id`),
  CONSTRAINT `FK_task_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspace` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 PACK_KEYS=0;

-- Data exporting was unselected.


-- Dumping structure for table heroku_81f3bf268364a4c.task_merger
DROP TABLE IF EXISTS `task_merger`;
CREATE TABLE IF NOT EXISTS `task_merger` (
  `task_id` int(10) DEFAULT NULL,
  `merger_id` int(10) DEFAULT NULL,
  `workspace_id` int(10) DEFAULT NULL,
  KEY `FK__task` (`task_id`),
  KEY `FK__merger` (`merger_id`),
  KEY `FK__workspace` (`workspace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Data exporting was unselected.


-- Dumping structure for table heroku_81f3bf268364a4c.task_postprocessor
DROP TABLE IF EXISTS `task_postprocessor`;
CREATE TABLE IF NOT EXISTS `task_postprocessor` (
  `task_id` int(10) NOT NULL DEFAULT '0',
  `postprocessor_id` int(10) NOT NULL DEFAULT '0',
  `workspace_id` int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`task_id`,`postprocessor_id`,`workspace_id`),
  KEY `FK_task_postprocessor_postprocessor` (`postprocessor_id`),
  KEY `FK_task_postprocessor_workspace` (`workspace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Data exporting was unselected.


-- Dumping structure for table heroku_81f3bf268364a4c.task_splitter
DROP TABLE IF EXISTS `task_splitter`;
CREATE TABLE IF NOT EXISTS `task_splitter` (
  `task_id` int(10) DEFAULT NULL,
  `splitter_id` int(10) DEFAULT NULL,
  `workspace_id` int(10) DEFAULT NULL,
  KEY `FK__task` (`task_id`),
  KEY `FK__merger` (`splitter_id`),
  KEY `FK__workspace` (`workspace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

-- Data exporting was unselected.


-- Dumping structure for table heroku_81f3bf268364a4c.task_task
DROP TABLE IF EXISTS `task_task`;
CREATE TABLE IF NOT EXISTS `task_task` (
  `source_task_id` int(10) NOT NULL DEFAULT '0',
  `target_task_id` int(10) NOT NULL DEFAULT '0',
  `workspace_id` int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`source_task_id`,`target_task_id`,`workspace_id`),
  KEY `FK__task_2` (`target_task_id`),
  KEY `FK_task_task_workspace` (`workspace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Data exporting was unselected.


-- Dumping structure for table heroku_81f3bf268364a4c.task_workspace
DROP TABLE IF EXISTS `task_workspace`;
CREATE TABLE IF NOT EXISTS `task_workspace` (
  `task_id` int(10) NOT NULL DEFAULT '0',
  `workspace_id` int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`task_id`,`workspace_id`),
  KEY `FK_task_workspace_workspace` (`workspace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Data exporting was unselected.


-- Dumping structure for table heroku_81f3bf268364a4c.user
DROP TABLE IF EXISTS `user`;
CREATE TABLE IF NOT EXISTS `user` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Data exporting was unselected.


-- Dumping structure for table heroku_81f3bf268364a4c.workspace
DROP TABLE IF EXISTS `workspace`;
CREATE TABLE IF NOT EXISTS `workspace` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `pos_x` int(10) NOT NULL DEFAULT '0',
  `pos_y` int(10) NOT NULL DEFAULT '0',
  `name` varchar(50) DEFAULT '0',
  `input_queue` text,
  `user_id` int(10) DEFAULT NULL,
  `macrotask_id` int(10) DEFAULT NULL,
  `description` text,
  `date_created` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_flowitems` text,
  `publish` int(10) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `FK__user` (`user_id`),
  KEY `FK_workspace_macrotask` (`macrotask_id`),
  CONSTRAINT `FK_workspace_macrotask` FOREIGN KEY (`macrotask_id`) REFERENCES `macrotask` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Data exporting was unselected.
/*!40014 SET FOREIGN_KEY_CHECKS=1 */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
