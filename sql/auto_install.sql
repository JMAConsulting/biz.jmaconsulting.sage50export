CREATE TABLE IF NOT EXISTS `civicrm_sage50_batches` (
   `id` INT NOT NULL AUTO_INCREMENT ,
   `batch_id` INT NOT NULL ,
  PRIMARY KEY (`id`),
  CONSTRAINT UI_batch_id UNIQUE (`batch_id`)
) ENGINE = InnoDB;
