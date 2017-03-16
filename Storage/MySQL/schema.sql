

DROP TABLE IF EXISTS bono_module_members;
CREATE TABLE bono_module_members (
  `id` INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  
  `email` varchar(30),
  `login` varchar(30),
  `password` varchar(90) COMMENT 'Password hash',
  
  `name` varchar(90),
  `phone` varchar(30),
  `address` TEXT,
  `subscriber` varchar(1),
  `key` varchar(100),
  `confirmed` varchar(1)
  
) DEFAULT CHARSET = UTF8;