<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\PlatformSeeder;
use App\Models\{User,Place,Review};
use App\Services\Totp;
use Illuminate\Support\Facades\DB;

class PlatformTest extends TestCase {
    use RefreshDatabase;
    protected function setUp():void {parent::setUp();$this->withoutVite();$this->seed(PlatformSeeder::class);}
    private function user(string $role='user'):User {return User::where('email',$role.'@kod.local')->firstOrFail();}
    public function test_public_catalog_has_verticals_and_journey():void {
        $this->get('/')->assertOk();
        $this->getJson('/platform/data')->assertOk()->assertJsonCount(3,'verticals')->assertJsonCount(8,'places')->assertJsonPath('journeys.0.name','Креативный Донецк');
    }
    public function test_unpublished_places_are_not_exposed():void {
        Place::first()->update(['status'=>'on_moderation']);
        $this->getJson('/platform/data')->assertJsonCount(7,'places');
        $this->get('/explore/'.Place::first()->slug)->assertNotFound();
    }
    public function test_guest_cannot_review_or_invest():void {
        $this->postJson('/platform/places/1/reviews',['rating'=>5,'text'=>'Хорошее место'])->assertUnauthorized();
        $this->postJson('/platform/investments',[])->assertUnauthorized();
    }
    public function test_owner_cannot_rate_own_place():void {
        $p=Place::whereNotNull('owner_id')->first();
        $this->actingAs($this->user('business'))->postJson('/platform/places/'.$p->id.'/reviews',['rating'=>5,'text'=>'Хорошее место'])->assertForbidden();
    }
    public function test_review_needs_moderation_and_updates_rating():void {
        $p=Place::first();
        $this->actingAs($this->user())->postJson('/platform/places/'.$p->id.'/reviews',['rating'=>5,'text'=>'Интересное место для прогулки'])->assertOk();
        $review=Review::firstOrFail();
        $this->getJson('/platform/places/'.$p->id.'/reviews')->assertExactJson([]);
        $this->actingAs($this->user('moderator'))->withSession(['staff_2fa'=>true])->post('/platform/reviews/'.$review->id.'/moderate',['approved'=>true])->assertRedirect();
        $this->assertEquals(5,(float)$p->fresh()->rating);
        $this->getJson('/platform/places/'.$p->id.'/reviews')->assertJsonCount(1);
    }
    public function test_review_deletion_is_authorized():void {
        $p=Place::first();
        $review=$p->reviews()->create(['user_id'=>$this->user()->id,'rating'=>4,'text'=>'Отзыв','status'=>'approved']);
        $this->actingAs($this->user('business'))->deleteJson('/platform/reviews/'.$review->id)->assertForbidden();
        $this->actingAs($this->user())->deleteJson('/platform/reviews/'.$review->id)->assertOk();
    }
    public function test_only_admin_creates_verticals_and_staff_need_second_factor():void {
        $data=['name'=>'Экотуризм','slug'=>'eco','color'=>'#547864','fields'=>['Сезон']];
        $this->actingAs($this->user())->post('/platform/verticals',$data)->assertForbidden();
        $this->actingAs($this->user('admin'))->get('/platform/workspace')->assertRedirect('/security');
        $this->withSession(['staff_2fa'=>true])->post('/platform/verticals',$data)->assertRedirect();
        $this->getJson('/platform/data')->assertJsonCount(4,'verticals');
    }
    public function test_business_submits_pending_place_and_cannot_edit_others():void {
        $p=Place::first();
        $data=['name'=>'Новая мастерская','description'=>'Новая мастерская в городе','address'=>'ул. Примерная, 1','vertical_id'=>1,'category_id'=>$p->category_id,'lat'=>48,'lng'=>37,'price_level'=>1,'visit_format'=>'booking','rights_confirmed'=>true];
        $this->actingAs($this->user())->post('/platform/places',$data)->assertRedirect();
        $this->assertDatabaseHas('places',['name'=>'Новая мастерская','status'=>'on_moderation','owner_id'=>$this->user()->id]);
        $this->put('/platform/places/'.$p->id,$data)->assertForbidden();
    }
    public function test_investment_is_private_and_curator_can_reply():void {
        $this->actingAs($this->user())->post('/platform/investments',['investment_site_id'=>1,'project'=>'Дизайн-студия','description'=>'Создание пространства','contact'=>'example@example.test'])->assertRedirect();
        $id=DB::table('investment_requests')->value('id');
        $this->put('/platform/investments/'.$id,['status'=>'approved'])->assertForbidden();
        $this->actingAs($this->user('curator'))->withSession(['staff_2fa'=>true])->put('/platform/investments/'.$id,['status'=>'in_progress','response'=>'Приглашаем обсудить проект'])->assertRedirect();
        $this->assertDatabaseHas('investment_requests',['id'=>$id,'status'=>'in_progress']);
    }
    public function test_personal_route_cannot_be_published_by_user():void {
        $this->actingAs($this->user())->postJson('/platform/journeys',['name'=>'Прогулка','duration'=>100,'stops'=>Place::take(2)->pluck('id')->toArray(),'published'=>true])->assertRedirect();
        $this->assertDatabaseHas('journeys',['name'=>'Прогулка','published'=>false]);
        $this->getJson('/platform/journeys')->assertJsonCount(1);
    }
    public function test_favorites_can_be_cleared():void {
        $this->actingAs($this->user())->postJson('/platform/favorites',['ids'=>[1,2]])->assertOk();
        $this->getJson('/platform/favorites')->assertJsonCount(2);
        $this->postJson('/platform/favorites',['ids'=>[]])->assertOk();
        $this->getJson('/platform/favorites')->assertExactJson([]);
    }
    public function test_guest_report_reaches_moderation():void {
        $this->postJson('/platform/places/1/reports',['type'=>'copyright','message'=>'Прошу проверить права на публикацию'])->assertOk();
        $this->assertDatabaseHas('place_reports',['type'=>'copyright','resolved'=>false]);
    }
    public function test_new_registration_is_not_business_or_staff():void {
        $this->post('/register',['name'=>'Анна','email'=>'anna@example.test','password'=>'LongPassword123','password_confirmation'=>'LongPassword123'])->assertRedirect('/');
        $u=User::where('email','anna@example.test')->first();
        $this->assertTrue($u->hasRole('user'));$this->assertFalse($u->hasRole('business'));
    }
    public function test_totp_known_vector_and_replay_rejected():void {
        $secret='GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $this->assertEquals('287082',Totp::code($secret,1));
        $this->assertEquals(1,Totp::verify($secret,'287082',null,59));
        $this->assertNull(Totp::verify($secret,'287082',1,59));
    }
    public function test_totp_enrollment_protects_staff_workspace():void {
        $secret=Totp::secret();$admin=$this->user('admin');
        $this->actingAs($admin)->withSession(['totp_pending'=>$secret])->post('/security',['code'=>Totp::code($secret,intdiv(time(),30))])->assertRedirect('/platform/workspace')->assertSessionHas('staff_2fa',true);
        $this->assertNotNull($admin->fresh()->two_factor_secret);
        $this->get('/platform/workspace')->assertOk();
    }
    public function test_owner_uploads_photo_with_pending_card():void {
        \Illuminate\Support\Facades\Storage::fake('public');
        $p=Place::first();
        $this->actingAs($this->user())->post('/platform/places',[
            'name'=>'Место с фото','description'=>'Описание места','address'=>'Адрес','vertical_id'=>1,'category_id'=>$p->category_id,
            'lat'=>48,'lng'=>37,'price_level'=>1,'visit_format'=>'individual','rights_confirmed'=>true,
            'photos'=>[\Illuminate\Http\UploadedFile::fake()->createWithContent('photo.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg=='))],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $created=Place::where('name','Место с фото')->firstOrFail();
        $this->assertCount(1,$created->photos);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists(str_replace('/storage/','',$created->photos->first()->path));
    }
}
