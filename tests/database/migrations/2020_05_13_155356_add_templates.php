<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected static $tableName = 'alice_page_templates';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            static::$tableName,
            static function (Blueprint $table) {
                $table->uuid()->primary();
                $table->string('title')->index();
                $table->string('language', 10)->nullable()->index();
                $table->string('group', 100)->index();
                $table->boolean('enabled')->default(true)->index();
                $table->boolean('removable')->default(true);
                $table->text('description')->nullable();
                $table->foreignUuid('site_id')->nullable()->index();
                $table->timestamps();
                $table->json('content_data')->default('{}');
                $table->jsonb('content_components')->default('{}')->index();
                ;

            //                (new \Fureev\Trees\Database\Migrate($builder, $table))->buildColumns();
            }
        );

        DB::statement('ALTER TABLE ' . static::$tableName . ' ADD COLUMN content_templates uuid[]');
        DB::statement('CREATE INDEX ON ' . static::$tableName . ' (content_templates)');

        DB::statement('CREATE INDEX ON ' . static::$tableName . ' (site_id, language, enabled) WHERE enabled = true');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(static::$tableName);
    }
};
