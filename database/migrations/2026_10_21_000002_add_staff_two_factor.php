<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void {Schema::table('users',function(Blueprint $t){$t->text('two_factor_secret')->nullable();$t->unsignedBigInteger('two_factor_counter')->nullable();});}
    public function down():void {Schema::table('users',fn(Blueprint $t)=>$t->dropColumn(['two_factor_secret','two_factor_counter']));}
};
