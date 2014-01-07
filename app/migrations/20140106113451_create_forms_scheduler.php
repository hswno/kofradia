<?php

use Phinx\Migration\AbstractMigration;

class CreateFormsScheduler extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
        $this->execute("
            INSERT INTO scheduler
            SET s_name = 'forms_clean', s_hours = 2, s_minutes = 10, s_seconds = 0,
                s_file = 'form/clean.php', s_description = 'Deletes old entries from forms-table.',
                s_active = 1");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
        $this->execute("
            DELETE FROM scheduler
            WHERE s_name = 'forms_clean'");
    }
}