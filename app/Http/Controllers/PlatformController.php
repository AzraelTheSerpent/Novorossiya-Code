<?php

namespace App\Http\Controllers;

use App\Models\{Place, Category, District, Event, Vertical, Journey, Review};
use App\Enums\{ModerationStatus, ReviewStatus};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PlatformController extends Controller
{
    public function index(Request $request, ?string $slug = null)
    {
        $place = $slug ? Place::approved()->where('slug', $slug)->firstOrFail() : null;
        $requested = $request->query('vertical');
        $initialVerticalId = $requested === 'all' ? 0 : (
            ($requested ? Vertical::where('slug', $requested)->value('id') : null)
            ?? $place?->vertical_id
            ?? Vertical::where('slug', 'tourism')->value('id')
            ?? 0
        );
        return Inertia::render('Platform/Explore', ['data' => $this->data(), 'selectedSlug' => $slug, 'initialVerticalId' => (int) $initialVerticalId]);
    }

    public function verticalPage(Vertical $vertical)
    {
        return view('vertical', [
            'vertical' => $vertical,
            'verticals' => Vertical::orderBy('id')->get(),
            'places' => Place::approved()->where('vertical_id', $vertical->id)
                ->with(['category', 'photos'])->orderByDesc('is_featured')->orderBy('name')->paginate(12),
        ]);
    }

    public function data(): array
    {
        return [
            'places' => Place::approved()->with(['category', 'photos'])->orderByDesc('is_featured')->get()->map(fn($p) => [
                'id'=>$p->id, 'slug'=>$p->slug, 'name'=>$p->name, 'description'=>$p->description,
                'summary'=>$p->short_description, 'address'=>$p->address,
                'lat'=>$p->lat === null ? null : (float)$p->lat, 'lng'=>$p->lng === null ? null : (float)$p->lng,
                'vertical_id'=>$p->vertical_id, 'category'=>$p->category?->name,
                'rating'=>(float)$p->rating, 'reviews_count'=>$p->reviews_count,
                'popularity'=>$p->views_count, 'price'=>$p->price_level,
                'cover'=>$p->cover_url, 'photos'=>$p->photos->pluck('path'), 'tags'=>$p->tags ?? [], 'details'=>$p->details ?? [],
                'hours'=>$p->working_hours ?? [], 'phone'=>$p->phone, 'site'=>$p->site,
                'format'=>$p->visit_format, 'verified_at'=>$p->verified_at?->format('d.m.Y'),
                'owner_id'=>$p->owner_id, 'promotion'=>$p->active_promotion,
            ])->values(),
            'verticals'=>Vertical::orderBy('id')->get(),
            'categories'=>Category::orderBy('sort')->get(['id', 'name']),
            'journeys'=>Journey::where('published', true)->with(['places'=>fn($q)=>$q->approved()])->get()->map(fn($j)=>[
                'id'=>$j->id, 'name'=>$j->name, 'description'=>$j->description, 'duration'=>$j->duration,
                'stops'=>$j->places->map(fn($p)=>['id'=>$p->id, 'minutes'=>$p->pivot->minutes])->values(),
            ]),
            'events'=>Event::where('status', ModerationStatus::Approved)->where('starts_at','>=',now()->startOfDay())
                ->orderBy('starts_at')->get(['id','title','description','place_id','starts_at','price','type']),
            'sites'=>DB::table('investment_sites')->get(),
            'updated_at'=>now()->toIso8601String(),
        ];
    }

    public function workspace(Request $request)
    {
        $u=$request->user(); $admin=$u->hasRole('admin');
        $moderator=$u->hasAnyRole(['admin','moderator']);
        $curator=$u->hasAnyRole(['admin','curator']);
        return Inertia::render('Platform/Workspace', [
            'data'=>$this->data(), 'isAdmin'=>$admin, 'isModerator'=>$moderator, 'isCurator'=>$curator,
            'ownPlaces'=>Place::when(!$admin,fn($q)=>$q->where('owner_id',$u->id))->get(),
            'queue'=>$moderator ? Place::where('status',ModerationStatus::OnModeration)->get() : [],
            'reviews'=>$moderator ? Review::where('status',ReviewStatus::Pending)->with('place:id,name')->get() : [],
            'reports'=>$moderator ? DB::table('place_reports')->where('resolved',false)->get() : [],
            'requests'=>DB::table('investment_requests')->when(!$curator,fn($q)=>$q->where('user_id',$u->id))->latest()->get(),
        ]);
    }

    public function vertical(Request $r)
    {
        abort_unless($r->user()->hasRole('admin'),403);
        $v=$r->validate(['name'=>'required|string|max:100','slug'=>'required|alpha_dash|max:80|unique:verticals',
            'description'=>'nullable|string|max:500','color'=>'required|regex:/^#[0-9a-fA-F]{6}$/',
            'fields'=>'nullable|array|max:15','fields.*'=>'required|string|max:80']);
        Vertical::create($v);
        return back()->with('success','Вертикаль добавлена. Она уже доступна в каталоге.');
    }

    public function storePlace(Request $r, ?Place $place=null)
    {
        if ($place) abort_unless($place->owner_id === $r->user()->id || $r->user()->hasRole('admin'),403);
        $v=$r->validate(['name'=>'required|string|max:180','description'=>'required|string|max:6000',
            'address'=>'required|string|max:255','vertical_id'=>'required|exists:verticals,id',
            'category_id'=>'required|exists:categories,id', 'lat'=>'required|numeric|between:-90,90',
            'lng'=>'required|numeric|between:-180,180','phone'=>'nullable|string|max:20',
            'price_level'=>'required|integer|between:1,4','visit_format'=>'required|in:individual,group,booking',
            'details'=>'nullable|array|max:15','details.*'=>'nullable|string|max:500',
            'promotion'=>'nullable|array:code,discount,conditions,expires_at',
            'promotion.code'=>'required_with:promotion|nullable|string|max:40|regex:/^[A-Za-zА-Яа-яЁё0-9_-]+$/u',
            'promotion.discount'=>'required_with:promotion|nullable|integer|between:1,90',
            'promotion.conditions'=>'required_with:promotion|nullable|string|max:500',
            'promotion.expires_at'=>'nullable|date_format:Y-m-d',
            'photos'=>'nullable|array|max:5','photos.*'=>'image|mimes:jpg,jpeg,png,webp|max:4096|dimensions:max_width=6000,max_height=6000',
            'rights_confirmed'=>'accepted']);
        unset($v['rights_confirmed'], $v['photos']);
        $v['short_description']=Str::limit($v['description'],250);
        $v['status']=ModerationStatus::OnModeration;
        if ($place) $place->update($v);
        else $place=Place::create($v+['slug'=>Str::slug($v['name']).'-'.Str::lower(Str::random(6)),
            'owner_id'=>$r->user()->id,'district_id'=>District::firstOrFail()->id]);
        foreach($r->file('photos',[]) as $i=>$photo) {
            $path=$photo->store('places','public');
            $place->photos()->create(['path'=>'/storage/'.$path,'sort'=>$place->photos()->count(),'is_cover'=>!$place->photos()->exists()]);
        }
        return back()->with('success','Карточка отправлена на модерацию.');
    }

    public function moderate(Request $r, Place $place)
    {
        abort_unless($r->user()->hasAnyRole(['admin','moderator']),403);
        $r->validate(['approved'=>'required|boolean']);
        $place->update(['status'=>$r->boolean('approved') ? ModerationStatus::Approved : ModerationStatus::Rejected,
            'verified_at'=>$r->boolean('approved') ? now() : null]);
        return back()->with('success','Решение сохранено.');
    }

    public function journey(Request $r)
    {
        $v=$r->validate(['name'=>'required|string|max:180','description'=>'nullable|string|max:2000',
            'duration'=>'required|integer|between:10,1440','stops'=>'required|array|min:2|max:20',
            'stops.*'=>'required|integer|distinct|exists:places,id','published'=>'boolean']);
        abort_if(Place::approved()->whereIn('id',$v['stops'])->count() !== count($v['stops']),422,'Маршрут должен содержать опубликованные места.');
        DB::transaction(function() use($r,$v) {
            $j=Journey::create(['user_id'=>$r->user()->id,'name'=>$v['name'],
                'slug'=>Str::uuid(),'description'=>$v['description'] ?? null,'duration'=>$v['duration'],
                'published'=>$r->user()->hasAnyRole(['admin','moderator']) && $r->boolean('published')]);
            foreach($v['stops'] as $i=>$id) $j->places()->attach($id,['position'=>$i,'minutes'=>30]);
        });
        return back()->with('success','Маршрут сохранён.');
    }

    public function myJourneys(Request $r)
    {
        return Journey::where('user_id',$r->user()->id)->with('places:id,name,slug')->get();
    }

    public function investment(Request $r)
    {
        $v=$r->validate(['investment_site_id'=>'required|exists:investment_sites,id','project'=>'required|string|max:180',
            'description'=>'required|string|max:4000','contact'=>'required|string|max:180']);
        DB::table('investment_requests')->insert($v+['user_id'=>$r->user()->id,'status'=>'new','created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Заявка передана куратору. Статус появится в кабинете.');
    }

    public function investmentStatus(Request $r, int $id)
    {
        abort_unless($r->user()->hasAnyRole(['admin','curator']),403);
        $v=$r->validate(['status'=>'required|in:new,in_progress,approved,rejected','response'=>'nullable|string|max:2000']);
        abort_unless(DB::table('investment_requests')->where('id',$id)->exists(),404);
        DB::table('investment_requests')->where('id',$id)->update($v+['updated_at'=>now()]);
        return back()->with('success','Статус заявки обновлён.');
    }

    public function report(Request $r, Place $place)
    {
        abort_unless($place->status === ModerationStatus::Approved,404);
        $v=$r->validate(['message'=>'required|string|min:5|max:2000','type'=>'required|in:inaccuracy,copyright,review']);
        DB::table('place_reports')->insert($v+['place_id'=>$place->id,'user_id'=>$r->user()?->id,'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Сообщение передано модератору.']);
    }

    public function resolveReport(Request $r, int $id)
    {
        abort_unless($r->user()->hasAnyRole(['admin','moderator']),403);
        DB::table('place_reports')->where('id',$id)->update(['resolved'=>true,'updated_at'=>now()]);
        return back();
    }

    public function reviews(Place $place)
    {
        abort_unless($place->status === ModerationStatus::Approved,404);
        return $place->reviews()->where('status',ReviewStatus::Approved)->latest()->get(['id','user_id','author_name','rating','text','owner_reply','created_at']);
    }

    public function review(Request $r, Place $place)
    {
        abort_unless($place->status === ModerationStatus::Approved,404);
        abort_if($place->owner_id === $r->user()->id,403,'Владелец не может оценить свой объект.');
        $v=$r->validate(['rating'=>'required|integer|between:1,5','text'=>'required|string|min:5|max:2000']);
        $place->reviews()->updateOrCreate(['user_id'=>$r->user()->id],$v+['author_name'=>$r->user()->name,'status'=>ReviewStatus::Pending]);
        $place->recalculateRating();
        return response()->json(['message'=>'Отзыв отправлен на модерацию.']);
    }

    public function deleteReview(Request $r, Review $review)
    {
        abort_unless($review->user_id === $r->user()->id || $r->user()->hasAnyRole(['admin','moderator']),403);
        $place=$review->place; $review->delete(); $place->recalculateRating();
        return response()->json(['message'=>'Отзыв удалён.']);
    }

    public function reply(Request $r, Review $review)
    {
        abort_unless($review->place->owner_id === $r->user()->id,403);
        $v=$r->validate(['owner_reply'=>'required|string|max:2000']);
        $review->owner_reply=$v['owner_reply']; $review->save();
        return response()->json(['message'=>'Ответ сохранён.']);
    }

    public function moderateReview(Request $r, Review $review)
    {
        abort_unless($r->user()->hasAnyRole(['admin','moderator']),403);
        $r->validate(['approved'=>'required|boolean']);
        $review->update(['status'=>$r->boolean('approved') ? ReviewStatus::Approved : ReviewStatus::Rejected]);
        $review->place->recalculateRating();
        return back();
    }

    public function favorites(Request $r)
    {
        $v=$r->validate(['ids'=>'present|array|max:500','ids.*'=>'integer|distinct|exists:places,id']);
        $r->user()->favorites()->syncWithPivotValues($v['ids'],['created_at'=>now()]);
        return response()->json(['message'=>'Избранное синхронизировано.']);
    }
}
