<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'ordine_chiamata')) {
                $table->dropColumn('ordine_chiamata');
            }
        });
    }
    
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('ordine_chiamata')->nullable()->after('is_admin');
        });
    }
};
