<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\{User, Vertical, Place, Category, District, Event, Journey};
use Spatie\Permission\Models\Role;

class PlatformSeeder extends Seeder {
    public function run():void {
        if(!app()->environment(['local','testing'])) throw new \RuntimeException('Демонстрационный сидер разрешён только в local/testing.');
        $this->call(RolePermissionSeeder::class);
        foreach(['user','curator'] as $role) Role::firstOrCreate(['name'=>$role,'guard_name'=>'web']);
        foreach([['admin','Администратор'],['moderator','Модератор'],['curator','Куратор'],['business','Владелец'],['user','Путешественник']] as [$role,$name]) {
            $u=User::firstOrCreate(['email'=>$role.'@kod.local'],['name'=>$name,'password'=>'DemoPass2026!','email_verified_at'=>now()]);
            $u->syncRoles([$role]);
        }
        $tourism=Vertical::updateOrCreate(['slug'=>'tourism'],['name'=>'Туризм','description'=>'Парки, музеи и места для отдыха.','color'=>'#547864','fields'=>['Доступность','Условия посещения']]);
        $creative=Vertical::updateOrCreate(['slug'=>'creative'],['name'=>'Креативные индустрии','description'=>'Пространства, мастерские и люди, которые создают.','color'=>'#b48b60','fields'=>['Услуги','Портфолио','Стажировки']]);
        $industrial=Vertical::updateOrCreate(['slug'=>'industrial'],['name'=>'Промышленный и гастротуризм','description'=>'Производства, экскурсии и локальные вкусы.','color'=>'#9d7162','fields'=>['Экскурсии','Продукция','Запись']]);
        $district=District::firstOrCreate(['slug'=>'center'],['name'=>'Центральный район','sort'=>0]);
        $owner=User::where('email','business@kod.local')->first();
        $rows=[
            ['creative-workshop','Мастерская «Точка»','Мастерские',$creative,'ул. Артёма, 44',48.009,37.805,'coffee.jpg','Керамика, разговоры и вещи с историей.','Открытая мастерская для знакомства с керамикой. Можно посмотреть работы, записаться на занятие и узнать о стажировке.',2,['Услуги'=>'Керамика и мастер-классы','Стажировки'=>'По предварительной заявке','Портфолио'=>'Коллекция изделий в мастерской']],
            ['city-museum','Музей городских историй','Музеи',$tourism,'ул. Университетская, 18',48.017,37.797,'hotel.jpg','Увидеть знакомый город по-новому.','Камерная экспозиция о городской жизни. В демонстрационной программе: архитектура, старые фотографии и истории жителей.',1,['Доступность'=>'Уточните условия у администратора']],
            ['coffee-pause','Кофейня «Пауза»','Кафе',$tourism,'бул. Пушкина, 12',48.003,37.803,'breakfast.jpg','Хороший кофе в середине вашей прогулки.','Небольшая кофейня для отдыха между остановками маршрута. В демонстрационном меню: сезонные напитки и локальная выпечка.',2,['Промокод'=>'KOD5 — демонстрационная скидка 5%']],
            ['riverside-park','Набережная для прогулок','Парки',$tourism,'ул. Набережная, 1',48.012,37.821,'park.jpg','Выдохнуть и никуда не спешить.','Пешеходная прогулка у воды с остановками для отдыха. Подойдёт для самостоятельного знакомства с городом.',1,['Доступность'=>'Открытое общественное пространство']],
            ['design-space','Пространство «Смена»','Креативные пространства',$creative,'ул. Челюскинцев, 54',48.021,37.814,'restaurant.jpg','Идеи, которым нужно больше места.','Демонстрационный профиль творческого пространства. Здесь представлены дизайн-студии, встречи и небольшие выставки.',2,['Услуги'=>'Дизайн, выставки, аренда пространства','Стажировки'=>'Принимаем заявки']],
            ['regional-bakery','Пекарня «Тёплый хлеб»','Производства',$industrial,'ул. Кирова, 8',47.996,37.789,'pizza.jpg','Как рождается вкус вашего утра.','Демонстрационный пример производственной экскурсии. Знакомство с ремесленным хлебом, разговор с пекарем и дегустация.',2,['Экскурсии'=>'По предварительной записи','Продукция'=>'Хлеб и выпечка','Запись'=>'Через владельца']],
            ['industrial-heritage','Индустриальный квартал','Индустриальное наследие',$industrial,'пр-т Мира, 10',48.032,37.8,'city-night.jpg','История труда в городских деталях.','Демонстрационная точка тематического маршрута по индустриальному наследию. Посещение только с согласованной экскурсией.',1,['Экскурсии'=>'Групповой формат','Запись'=>'Обязательна']],
            ['flower-studio','Студия «Травы»','Студии',$creative,'бул. Шевченко, 15',48.007,37.83,'flowers.jpg','Флористика как способ увидеть красоту.','Демонстрационная флористическая студия. Мастер-классы, сезонные композиции и знакомство с профессией.',2,['Услуги'=>'Композиции и мастер-классы','Стажировки'=>'Есть вводная программа']],
        ];
        $places=[];
        foreach($rows as $i=>[$slug,$name,$cat,$v,$address,$lat,$lng,$image,$summary,$description,$price,$details]) {
            $category=Category::firstOrCreate(['slug'=>'kod-'.md5($cat)],['name'=>$cat,'sort'=>$i]);
            $p=Place::updateOrCreate(['slug'=>$slug],['name'=>$name,'category_id'=>$category->id,'district_id'=>$district->id,
                'owner_id'=>$i<3?$owner->id:null,'vertical_id'=>$v->id,'address'=>$address,'lat'=>$lat,'lng'=>$lng,
                'description'=>$description,'short_description'=>$summary,'status'=>'approved','price_level'=>$price,
                'is_featured'=>$i===0,'rating'=>0,'views_count'=>100-$i*7,'tags'=>[$cat,$v->name,'Донецк'],
                'details'=>$details,'visit_format'=>$v->id===$industrial->id?'booking':'individual','verified_at'=>now(),
                'working_hours'=>array_fill_keys(['mon','tue','wed','thu','fri','sat','sun'],$i===3?'24/7':'09:00-21:00')]);
            $p->photos()->updateOrCreate(['is_cover'=>true],['path'=>'/assets/'.$image,'sort'=>0]);
            $places[]=$p;
        }
        $journey=Journey::updateOrCreate(['slug'=>'creative-donetsk'],['name'=>'Креативный Донецк',
            'description'=>'Музей, творческая мастерская и пауза за кофе. Три остановки для знакомства с городом.','duration'=>150,'published'=>true]);
        $journey->places()->sync([$places[1]->id=>['position'=>0,'minutes'=>45],$places[0]->id=>['position'=>1,'minutes'=>50],$places[2]->id=>['position'=>2,'minutes'=>30]]);
        foreach([['Выставка «Город в деталях»',1,'exhibition',0],['Открытая мастерская: керамика',0,'exhibition',1],['Вечер локальной музыки',4,'concert',3]] as $i=>[$title,$place,$type,$days]) {
            Event::updateOrCreate(['slug'=>'kod-event-'.$i],['title'=>$title,'description'=>'Демонстрационное событие. Даты и условия перед посещением необходимо уточнять у организатора.','type'=>$type,'place_id'=>$places[$place]->id,'starts_at'=>now()->addDays($days)->setTime(18,0),'price'=>$i===0?'Бесплатно':'По записи','status'=>'approved']);
        }
        foreach([['Пространство для творческой студии',120,'Аренда'],['Площадка для локального производства',450,'Партнёрство']] as [$name,$area,$format]) {
            DB::table('investment_sites')->updateOrInsert(['name'=>$name],['description'=>'Демонстрационная площадка для обсуждения проекта с куратором. Условия и доступность требуют проверки.','address'=>'Донецк, адрес уточняется у куратора','area'=>$area,'format'=>$format,'created_at'=>now(),'updated_at'=>now()]);
        }
    }
}
