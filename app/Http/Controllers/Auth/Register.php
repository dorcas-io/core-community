<?php

namespace App\Http\Controllers\Auth;


use App\Events\AccountRegistered;
use App\Exceptions\RecordNotFoundException;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Partner;
use App\Models\Plan;
use App\Transformers\UserTransformer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\ClientRepository;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use Prophecy\Exception\Call\UnexpectedCallException;


class Register extends Controller
{
    /**
     * @param Request $request
     * @param Manager $fractal
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request, Manager $fractal)
    {
        $this->validate($request, [
            'client_id' => 'required|numeric',
            'client_secret' => 'required|string',
            'email' => 'required|email|max:80|unique:users,email',
            'password' => 'required|min:8',
            'firstname' => 'required|max:30',
            'lastname' => 'required|max:80',
            'company' => 'nullable|max:100',
            'phone' => 'required|max:30',
            'plan' => 'nullable|string',
            'plan_type' => 'nullable|string|in:monthly,yearly',
            'module' => 'nullable|string|in:all,customers,people,finance,ecommerce,sales',
            'feature_select' => 'required|max:50',
            'partner' => 'nullable|string',
            'is_professional' => 'nullable|numeric',
            'is_vendor' => 'nullable|numeric',
            'trigger_event' => 'nullable|numeric|in:0,1'
        ]);
        // $request->request->set('client_id',env('CLIENT_ID'));
        // $request->request->set('client_secret',env('CLIENT_SECRET'));

        if($request->input('installer') === true || $request->input('installer') === "true") {
          $this->validate($request, ['domain' => 'required|max:100']);
        }


        # validate the request
        validate_api_client($request);

        # validate the request
        if (strlen($request->input('lastname')) > 30) {
            $request->request->set('lastname', substr($request->input('lastname'), 0, 30));
        }
        $planName = $request->input('plan', 'starter');
        # get the plan name
        $plan = Plan::where('name', 'like', $planName)->first();
        # get the plan'
        if (empty($plan)) {
            throw new \UnexpectedValueException('We could not find the selected plan: '.$planName);
        }

        $base_modules = ['dashboard','settings','addons','integrations','appstore'];
        
        //$possible_modules = ['customers','people','finance','ecommerce','sales'];
        //$module_preference = array_merge($request->input('module', []),$base_modules);
        //$module = in_array($module_preference, $possible_modules) ? $module_preference : "all";

        $possible_features = ['selling_online','payroll','finance','all'];
        $feature_modules = [
            'selling_online' => ['customers','ecommerce','sales'],
            'payroll' => ['people'],
            'finance' => ['finance'],
             'all' => ['customers','people','finance','ecommerce','sales']
        ];
        
        $feature_selected = $request->input('feature_select');

        $feature_converted = !empty($feature_selected) ? $feature_modules[$feature_selected] : [];

        $module = array_merge($feature_converted,$base_modules);
        
        #get the prefered module and feature


        $user = null;
        $company = null;
        $partner = null;
        # the models

        if ($request->has('partner')) {
            $partner = Partner::where(function ($query) use ($request) {
                                    $query->where('uuid', $request->partner)
                                            ->orWhere('slug', $request->partner);
                                })
                                ->first();
        }
        DB::transaction(function () use (&$company, $plan, $request, $partner, &$user, $module) {

            //$expiry = $plan->price_monthly === 0 ? null : Carbon::now()->subDay()->endOfDay();

            $paidExpiry = Carbon::now()->addMonth()->subDay()->endOfDay(); //example of monthly payment. calculate properly for paid plans

            $expiry = $plan->price_monthly === 0 ? Carbon::now()->addYear()->subDay()->endOfDay() : $paidExpiry; //make 1 year for all new users and real data  for others

            $configurations = [];
            $configurations['module_preference'] = $module;
            $configurations['ui_setup'] = collect([$module])->all();
            $companyName = $request->has('company') ? $request->company : $request->firstname .' '.$request->lastname;
            $company = new Company([
                'name' => $companyName,
                'plan_id' => $plan->id,
                'plan_type' => $request->input('plan_type', 'monthly'),
                'access_expires_at' => empty($expiry) ? $expiry :  $expiry->format('Y-m-d H:i:s'),
                'extra_data' => $configurations,
                'prefix' => prefixGenerator()
            ]);
            $company->save();

            # save the company
            $user = $company->users()->create([
                'firstname' => $request->input('firstname'),
                'lastname' => $request->input('lastname'),
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
                'phone' => $request->input('phone'),
                'is_professional' => $request->has('is_professional') ? 1 : 0,
                'is_vendor' => $request->has('is_vendor') ? 1 : 0,
                'partner_id' => empty($partner) ? null : $partner->id
            ]);

            if ($request->input('installer') === true || $request->input('installer') === "true") {
              $this->registerHubUser($company,$user);

              $domain = $request->input('domain'); //verify later
              $this->registerBusinessDomain($company,$domain);
            }

            # we need to create the user
        });


        $triggerEvent = (bool) ($request->has('trigger_event') ? (int)  $request->input('trigger_event', 1) : 1);
        # get the event trigger status
        if ($triggerEvent && $user) {
            event(new AccountRegistered($user, $company));
        }
        $transformer = new UserTransformer();
        $transformer->setDefaultIncludes(['company']);
        $resource = new Item($user, $transformer, 'user');
        return response()->json($fractal->createData($resource)->toArray());
    }
    
    private function registerHubUser($data,$dorcasUser)
    {
      try {
         $db = DB::connection('hub_mysql');
         DB::transaction(function () use(&$data,$db,&$dorcasUser) {
           $company = new Company;
             $company->setConnection('hub_mysql');
             $company =  $company->create([
                'uuid' => $data->id,
                'reg_number' => $data->registration,
                'name' => $data->name,
                'phone' => $data->phone,
                'email' => $data->email,
                'website' => $data->website
              ]);
              $db->table('users')->insert([
                'uuid' => $dorcasUser->id,
                'firstname' => $dorcasUser->firstname,
                'lastname' => $dorcasUser->lastname,
                'email' => $dorcasUser->email,
                'password' => $dorcasUser->password,
                'company_id' => $company->id,
                'gender' => $dorcasUser->gender,
                'photo_url' => $dorcasUser->photo,
                'is_verified' => (int) $dorcasUser->is_verified
              ]);

            });

        return true;

      }
      catch (\Exception $e){
        throw  new \Exception($e->getMessage());
      }
    }

    public function registerBusinessDomain($company,$domain)
    {
      try {
        $field = $company->domainIssuances()->create([
            'prefix' => $domain,
            'domain_id' => null
        ]);
      }
      catch (\Exception $e){
        throw  new \Exception($e->getMessage());
      }
    }

}