<?php

use Phinx\Migration\AbstractMigration;

class CreateGitHubTable extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
        $this->execute("
            CREATE TABLE IF NOT EXISTS github_log (
                gl_id INT(11) unsigned NOT NULL AUTO_INCREMENT,
                gl_time INT(10) unsigned NOT NULL,
                gl_event_type VARCHAR(30) NOT NULL,
                gl_contents TEXT NOT NULL COMMENT 'serialized() representation of GitHub-objects',
                gl_log_count TINYINT(3) unsigned NOT NULL,
                PRIMARY KEY (gl_id)
            ) ENGINE=InnoDB");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {

    }
}