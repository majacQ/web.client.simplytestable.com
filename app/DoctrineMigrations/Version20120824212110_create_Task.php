<?php

namespace Application\Migrations;

use SimplyTestable\BaseMigrationsBundle\Migration\BaseMigration,
    Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your need!
 */
class Version20120824212110_create_Task extends BaseMigration
{
    public function up(Schema $schema)
    {        
        $this->statements['mysql'] = array(
            "CREATE TABLE Task (
                id INT AUTO_INCREMENT NOT NULL,
                taskId INT NOT NULL,
                url LONGTEXT NOT NULL,
                state VARCHAR(255) NOT NULL,
                worker VARCHAR(255) DEFAULT NULL,
                type VARCHAR(255) NOT NULL,
                timePeriod_id INT DEFAULT NULL,
                test_id INT NOT NULL,
                UNIQUE INDEX UNIQ_F24C741BE43FFED1 (timePeriod_id),
                INDEX IDX_F24C741B1E5D0459 (test_id),
                PRIMARY KEY(id))
                DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDB",
            "ALTER TABLE Task ADD CONSTRAINT FK_F24C741BE43FFED1 FOREIGN KEY (timePeriod_id) REFERENCES TimePeriod (id)",
            "ALTER TABLE Task ADD CONSTRAINT FK_F24C741B1E5D0459 FOREIGN KEY (test_id) REFERENCES Test (id)"            
        );
        
        $this->statements['sqlite'] = array(
            "CREATE TABLE Task (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                taskId INT NOT NULL,
                url LONGTEXT NOT NULL COLLATE NOCASE,
                state VARCHAR(255) NOT NULL COLLATE NOCASE,
                worker VARCHAR(255) DEFAULT NULL COLLATE NOCASE,
                type VARCHAR(255) NOT NULL COLLATE NOCASE,
                timePeriod_id INT DEFAULT NULL,
                test_id INT NOT NULL,
                output_id INT DEFAULT NULL,
                FOREIGN KEY (timePeriod_id) REFERENCES TimePeriod (id),
                FOREIGN KEY (test_id) REFERENCES Test (id),
                FOREIGN KEY (output_id) REFERENCES TaskOutput (id))",
            "CREATE UNIQUE INDEX UNIQ_F24C741BE43FFED1 ON Task (timePeriod_id)",
            "CREATE INDEX IDX_F24C741B1E5D0459 ON Task (test_id)",
            "CREATE INDEX IDX_F24C741BDE097880 ON Task (output_id)"
        );
        
        parent::up($schema);
    }

    public function down(Schema $schema)
    {
        $this->addCommonStatement("DROP TABLE Task");        
        parent::down($schema);
    }
}
