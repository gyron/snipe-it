<?php
/**
 * This controller handles all actions related to Accessories for
 * the Snipe-IT Asset Management application.
 *
 * PHP version 5.5.9
 * @package    Snipe-IT
 * @version    v3.0
 */

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\Accessory;
use App\Models\Actionlog;
use App\Models\Company;
use App\Models\Setting;
use App\Models\User;
use Config;
use DB;
use Input;
use Lang;
use Mail;
use Redirect;
use Slack;
use Str;
use View;
use Auth;

/**
 * This class controls all actions related to accessories
 */
class AccessoriesController extends Controller
{

    /**
    * Returns a view that invokes the ajax tables which actually contains
    * the content for the accessories listing, which is generated in getDatatable.
    *
    * @author [A. Gianotto] [<snipe@snipe.net>]
    * @see AccessoriesController::getDatatable() method that generates the JSON response
    * @since [v1.0]
    * @return View
    */
    public function getIndex()
    {
        return View::make('accessories/index');
    }


  /**
   * Returns a view with a form to create a new Accessory.
   *
   * @author [A. Gianotto] [<snipe@snipe.net>]
   * @return View
   */
    public function getCreate()
    {
        // Show the page
        $category_list = array('' => '') + DB::table('categories')->where('category_type', '=', 'accessory')->whereNull('deleted_at')->orderBy('name', 'ASC')->lists('name', 'id');
        $company_list = Helper::companyList();
        $location_list = Helper::locationsList();
        return View::make('accessories/edit')
          ->with('accessory', new Accessory)
          ->with('category_list', $category_list)
          ->with('company_list', $company_list)
          ->with('location_list', $location_list);
    }


  /**
   * Validate and save new Accessory from form post
   *
   * @author [A. Gianotto] [<snipe@snipe.net>]
   * @return Redirect
   */
    public function postCreate()
    {

        // create a new model instance
        $accessory = new Accessory();

        // Update the accessory data
        $accessory->name                  = e(Input::get('name'));
        $accessory->category_id               = e(Input::get('category_id'));
        $accessory->location_id               = e(Input::get('location_id'));
        $accessory->min_amt               = e(Input::get('min_amt'));
        $accessory->company_id              = Company::getIdForCurrentUser(Input::get('company_id'));
        $accessory->order_number            = e(Input::get('order_number'));

        if (e(Input::get('purchase_date')) == '') {
            $accessory->purchase_date       =  null;
        } else {
            $accessory->purchase_date       = e(Input::get('purchase_date'));
        }

        if (e(Input::get('purchase_cost')) == '0.00') {
            $accessory->purchase_cost       =  null;
        } else {
            $accessory->purchase_cost       = e(Input::get('purchase_cost'));
        }

        $accessory->qty                       = e(Input::get('qty'));
        $accessory->user_id               = Auth::user()->id;

        // Was the accessory created?
        if ($accessory->save()) {
            // Redirect to the new accessory  page
            return Redirect::to("admin/accessories")->with('success', Lang::get('admin/accessories/message.create.success'));
        }


        return Redirect::back()->withInput()->withErrors($accessory->getErrors());
    }

  /**
   * Return view for the Accessory update form, prepopulated with existing data
   *
   * @author [A. Gianotto] [<snipe@snipe.net>]
   * @param  int  $accessoryId
   * @return View
   */
    public function getEdit($accessoryId = null)
    {
        // Check if the accessory exists
        if (is_null($accessory = Accessory::find($accessoryId))) {
            // Redirect to the blogs management page
            return Redirect::to('admin/accessories')->with('error', Lang::get('admin/accessories/message.does_not_exist'));
        } elseif (!Company::isCurrentUserHasAccess($accessory)) {
            return Redirect::to('admin/accessories')->with('error', Lang::get('general.insufficient_permissions'));
        }

        $category_list = array('' => '') + DB::table('categories')->where('category_type', '=', 'accessory')->whereNull('deleted_at')->orderBy('name', 'ASC')->lists('name', 'id');
        $company_list = Helper::companyList();
        $location_list = Helper::locationsList();

        return View::make('accessories/edit', compact('accessory'))
          ->with('category_list', $category_list)
          ->with('company_list', $company_list)
          ->with('location_list', $location_list);
    }


  /**
   * Save edited Accessory from form post
   *
   * @author [A. Gianotto] [<snipe@snipe.net>]
   * @param  int  $accessoryId
   * @return Redirect
   */
    public function postEdit($accessoryId = null)
    {
      // Check if the blog post exists
        if (is_null($accessory = Accessory::find($accessoryId))) {
            // Redirect to the blogs management page
            return Redirect::to('admin/accessories')->with('error', Lang::get('admin/accessories/message.does_not_exist'));
        } elseif (!Company::isCurrentUserHasAccess($accessory)) {
            return Redirect::to('admin/accessories')->with('error', Lang::get('general.insufficient_permissions'));
        }

      // Update the accessory data
        $accessory->name                    = e(Input::get('name'));

        if (e(Input::get('location_id')) == '') {
            $accessory->location_id = null;
        } else {
            $accessory->location_id     = e(Input::get('location_id'));
        }
        $accessory->min_amt             = e(Input::get('min_amt'));
        $accessory->category_id             = e(Input::get('category_id'));
        $accessory->company_id              = Company::getIdForCurrentUser(Input::get('company_id'));
        $accessory->order_number            = e(Input::get('order_number'));

        if (e(Input::get('purchase_date')) == '') {
            $accessory->purchase_date       =  null;
        } else {
            $accessory->purchase_date       = e(Input::get('purchase_date'));
        }

        if (e(Input::get('purchase_cost')) == '0.00') {
            $accessory->purchase_cost       =  null;
        } else {
            $accessory->purchase_cost       = e(Input::get('purchase_cost'));
        }

        $accessory->qty                     = e(Input::get('qty'));

      // Was the accessory created?
        if ($accessory->save()) {
            // Redirect to the new accessory page
            return Redirect::to("admin/accessories")->with('success', Lang::get('admin/accessories/message.update.success'));
        }


        return Redirect::back()->withInput()->withErrors($accessory->getErrors());

    }

  /**
   * Delete the given accessory.
   *
   * @author [A. Gianotto] [<snipe@snipe.net>]
   * @param  int  $accessoryId
   * @return Redirect
   */
    public function getDelete($accessoryId)
    {
        // Check if the blog post exists
        if (is_null($accessory = Accessory::find($accessoryId))) {
            // Redirect to the blogs management page
            return Redirect::to('admin/accessories')->with('error', Lang::get('admin/accessories/message.not_found'));
        } elseif (!Company::isCurrentUserHasAccess($accessory)) {
            return Redirect::to('admin/accessories')->with('error', Lang::get('general.insufficient_permissions'));
        }


        if ($accessory->hasUsers() > 0) {
             return Redirect::to('admin/accessories')->with('error', Lang::get('admin/accessories/message.assoc_users', array('count'=> $accessory->hasUsers())));
        } else {
            $accessory->delete();

            // Redirect to the locations management page
            return Redirect::to('admin/accessories')->with('success', Lang::get('admin/accessories/message.delete.success'));

        }
    }



  /**
  * Returns a view that invokes the ajax table which  contains
  * the content for the accessory detail view, which is generated in getDataView.
  *
  * @author [A. Gianotto] [<snipe@snipe.net>]
  * @param  int  $accessoryId
  * @see AccessoriesController::getDataView() method that generates the JSON response
  * @since [v1.0]
  * @return View
  */
    public function getView($accessoryID = null)
    {
        $accessory = Accessory::find($accessoryID);

        if (isset($accessory->id)) {

            if (!Company::isCurrentUserHasAccess($accessory)) {
                return Redirect::to('admin/accessories')->with('error', Lang::get('general.insufficient_permissions'));
            } else {
                return View::make('accessories/view', compact('accessory'));
            }
        } else {
            // Prepare the error message
            $error = Lang::get('admin/accessories/message.does_not_exist', compact('id'));

            // Redirect to the user management page
            return Redirect::route('accessories')->with('error', $error);
        }


    }

  /**
   * Return the form to checkout an Accessory to a user.
   *
   * @author [A. Gianotto] [<snipe@snipe.net>]
   * @param  int  $accessoryId
   * @return View
   */
    public function getCheckout($accessoryId)
    {
        // Check if the accessory exists
        if (is_null($accessory = Accessory::find($accessoryId))) {
            // Redirect to the accessory management page with error
            return Redirect::to('accessories')->with('error', Lang::get('admin/accessories/message.not_found'));
        } elseif (!Company::isCurrentUserHasAccess($accessory)) {
            return Redirect::to('admin/accessories')->with('error', Lang::get('general.insufficient_permissions'));
        }

        // Get the dropdown of users and then pass it to the checkout view
        $users_list = array('' => 'Select a User') + DB::table('users')->select(DB::raw('concat(last_name,", ",first_name," (",username,")") as full_name, id'))->whereNull('deleted_at')->orderBy('last_name', 'asc')->orderBy('first_name', 'asc')->lists('full_name', 'id');

        return View::make('accessories/checkout', compact('accessory'))->with('users_list', $users_list);

    }

  /**
   * Save the Accessory checkout information.
   *
   * If Slack is enabled and/or asset acceptance is enabled, it will also
   * trigger a Slack message and send an email.
   *
   * @author [A. Gianotto] [<snipe@snipe.net>]
   * @param  int  $accessoryId
   * @return Redirect
   */
    public function postCheckout($accessoryId)
    {
      // Check if the accessory exists
        if (is_null($accessory = Accessory::find($accessoryId))) {
            // Redirect to the accessory management page with error
            return Redirect::to('accessories')->with('error', Lang::get('admin/accessories/message.user_not_found'));
        } elseif (!Company::isCurrentUserHasAccess($accessory)) {
            return Redirect::to('admin/accessories')->with('error', Lang::get('general.insufficient_permissions'));
        }

        if (!$user = User::find(Input::get('assigned_to'))) {
            return Redirect::to('admin/accessories')->with('error', Lang::get('admin/accessories/message.not_found'));
        }

      // Update the accessory data
        $accessory->assigned_to                 = e(Input::get('assigned_to'));

        $accessory->users()->attach($accessory->id, array(
        'accessory_id' => $accessory->id,
        'assigned_to' => e(Input::get('assigned_to'))));

        $admin_user = Auth::user();

        $logaction = new Actionlog();
        $logaction->accessory_id = $accessory->id;
        $logaction->checkedout_to = $accessory->assigned_to;
        $logaction->asset_type = 'accessory';
        $logaction->location_id = Auth::user()->location_id;
        $logaction->user_id = $admin_user->id;
        $logaction->note = e(Input::get('note'));



        $settings = Setting::getSettings();

        if ($settings->slack_endpoint) {


            $slack_settings = [
              'username' => $settings->botname,
              'channel' => $settings->slack_channel,
              'link_names' => true
            ];

            $client = new \Maknz\Slack\Client($settings->slack_endpoint, $slack_settings);

            try {
                $client->attach([
                'color' => 'good',
                'fields' => [
                  [
                  'title' => 'Checked Out:',
                  'value' => strtoupper($logaction->asset_type).' <'.config('app.url').'/admin/accessories/'.$accessory->id.'/view'.'|'.$accessory->name.'> checked out to <'.config('app.url').'/admin/users/'.$user->id.'/view|'.$user->fullName().'> by <'.config('app.url').'/admin/users/'.$admin_user->id.'/view'.'|'.$admin_user->fullName().'>.'
                  ],
                  [
                      'title' => 'Note:',
                      'value' => e($logaction->note)
                  ],
                ]
                ])->send('Accessory Checked Out');
            } catch (Exception $e) {

            }

        }



        $log = $logaction->logaction('checkout');

        $accessory_user = DB::table('accessories_users')->where('assigned_to', '=', $accessory->assigned_to)->where('accessory_id', '=', $accessory->id)->first();

        $data['log_id'] = $logaction->id;
        $data['eula'] = $accessory->getEula();
        $data['first_name'] = $user->first_name;
        $data['item_name'] = $accessory->name;
        $data['checkout_date'] = $logaction->created_at;
        $data['item_tag'] = '';
        $data['expected_checkin'] = '';
        $data['note'] = $logaction->note;
        $data['require_acceptance'] = $accessory->requireAcceptance();


        if (($accessory->requireAcceptance()=='1')  || ($accessory->getEula())) {

            Mail::send('emails.accept-accessory', $data, function ($m) use ($user) {
                $m->to($user->email, $user->first_name . ' ' . $user->last_name);
                $m->subject('Confirm accessory delivery');
            });
        }

      // Redirect to the new accessory page
        return Redirect::to("admin/accessories")->with('success', Lang::get('admin/accessories/message.checkout.success'));



    }


  /**
  * Check the accessory back into inventory
  *
  * @author [A. Gianotto] [<snipe@snipe.net>]
  * @param  int  $accessoryId
  * @return View
  **/
    public function getCheckin($accessoryUserId = null, $backto = null)
    {
        // Check if the accessory exists
        if (is_null($accessory_user = DB::table('accessories_users')->find($accessoryUserId))) {
            // Redirect to the accessory management page with error
            return Redirect::to('admin/accessories')->with('error', Lang::get('admin/accessories/message.not_found'));
        }

        $accessory = Accessory::find($accessory_user->accessory_id);

        if (!Company::isCurrentUserHasAccess($accessory)) {
            return Redirect::to('admin/accessories')->with('error', Lang::get('general.insufficient_permissions'));
        } else {
            return View::make('accessories/checkin', compact('accessory'))->with('backto', $backto);
        }
    }


  /**
  * Check in the item so that it can be checked out again to someone else
  *
  * @uses Accessory::checkin_email() to determine if an email can and should be sent
  * @author [A. Gianotto] [<snipe@snipe.net>]
  * @param  int  $accessoryId
  * @return Redirect
  **/
    public function postCheckin($accessoryUserId = null, $backto = null)
    {
      // Check if the accessory exists
        if (is_null($accessory_user = DB::table('accessories_users')->find($accessoryUserId))) {
            // Redirect to the accessory management page with error
            return Redirect::to('admin/accessories')->with('error', Lang::get('admin/accessories/message.not_found'));
        }


        $accessory = Accessory::find($accessory_user->accessory_id);

        if (!Company::isCurrentUserHasAccess($accessory)) {
            return Redirect::to('admin/accessories')->with('error', Lang::get('general.insufficient_permissions'));
        }

        $logaction = new Actionlog();
        $logaction->checkedout_to = $accessory_user->assigned_to;
        $return_to = $accessory_user->assigned_to;
        $admin_user = Auth::user();


      // Was the accessory updated?
        if (DB::table('accessories_users')->where('id', '=', $accessory_user->id)->delete()) {

            $logaction->accessory_id = $accessory->id;
            $logaction->location_id = null;
            $logaction->asset_type = 'accessory';
            $logaction->user_id = $admin_user->id;
            $logaction->note = e(Input::get('note'));

            $settings = Setting::getSettings();

            if ($settings->slack_endpoint) {


                $slack_settings = [
                'username' => $settings->botname,
                'channel' => $settings->slack_channel,
                'link_names' => true
                ];

                $client = new \Maknz\Slack\Client($settings->slack_endpoint, $slack_settings);

                try {
                    $client->attach([
                        'color' => 'good',
                        'fields' => [
                            [
                                'title' => 'Checked In:',
                                'value' => strtoupper($logaction->asset_type).' <'.config('app.url').'/admin/accessories/'.$accessory->id.'/view'.'|'.$accessory->name.'> checked in by <'.config('app.url').'/admin/users/'.$admin_user->id.'/view'.'|'.$admin_user->fullName().'>.'
                            ],
                            [
                                'title' => 'Note:',
                                'value' => e($logaction->note)
                            ],

                        ]
                    ])->send('Accessory Checked In');

                } catch (Exception $e) {

                }

            }


            $log = $logaction->logaction('checkin from');

            if (!is_null($accessory_user->assigned_to)) {
                $user = User::find($accessory_user->assigned_to);
            }

            $data['log_id'] = $logaction->id;
            $data['first_name'] = $user->first_name;
            $data['item_name'] = $accessory->name;
            $data['checkin_date'] = $logaction->created_at;
            $data['item_tag'] = '';
            $data['note'] = $logaction->note;

            if (($accessory->checkin_email()=='1')) {

                Mail::send('emails.checkin-asset', $data, function ($m) use ($user) {
                    $m->to($user->email, $user->first_name . ' ' . $user->last_name);
                    $m->subject('Confirm Accessory Checkin');
                });
            }

            if ($backto=='user') {
                return Redirect::to("admin/users/".$return_to.'/view')->with('success', Lang::get('admin/accessories/message.checkin.success'));
            } else {
                return Redirect::to("admin/accessories/".$accessory->id."/view")->with('success', Lang::get('admin/accessories/message.checkin.success'));
            }
        }

        // Redirect to the accessory management page with error
        return Redirect::to("admin/accessories")->with('error', Lang::get('admin/accessories/message.checkin.error'));
    }

  /**
  * Generates the JSON response for accessories listing view.
  *
  * Example:
  * {
  *  "actions": "(links to available actions)",
  *  "category": "(link to category)",
  *  "companyName": "My Company",
  *  "location":  "My Location",
  *  "min_amt": 2,
  *  "name":  "(link to accessory),
  *  "numRemaining": 6,
  *  "order_number": null,
  *  "purchase_cost": "0.00",
  *  "purchase_date": null,
  *  "qty": 7
  *  },
  *
  * The names of the fields in the returns JSON correspond directly to the the
  * names of the fields in the bootstrap-tables in the view.
  *
  * For debugging, see at /api/accessories/list
  *
  * @author [A. Gianotto] [<snipe@snipe.net>]
  * @param  int  $accessoryId
  * @return string JSON containing accessories and their associated atrributes.
  **/
    public function getDatatable()
    {
        $accessories = Accessory::select('accessories.*')->with('category', 'company')
        ->whereNull('accessories.deleted_at');

        if (Input::has('search')) {
            $accessories = $accessories->TextSearch(Input::get('search'));
        }

        if (Input::has('offset')) {
            $offset = e(Input::get('offset'));
        } else {
            $offset = 0;
        }

        if (Input::has('limit')) {
            $limit = e(Input::get('limit'));
        } else {
            $limit = 50;
        }


        $allowed_columns = ['name','min_amt','order_number','purchase_date','purchase_cost','companyName','category'];
        $order = Input::get('order') === 'asc' ? 'asc' : 'desc';
        $sort = in_array(Input::get('sort'), $allowed_columns) ? Input::get('sort') : 'created_at';

        switch ($sort) {
            case 'category':
                $accessories = $accessories->OrderCategory($order);
                break;
            case 'companyName':
                $accessories = $accessories->OrderCompany($order);
                break;
            default:
                $accessories = $accessories->orderBy($sort, $order);
                break;
        }

        $accessCount = $accessories->count();
        $accessories = $accessories->skip($offset)->take($limit)->get();

        $rows = array();

        foreach ($accessories as $accessory) {
            $actions = '<nobr><a href="'.route('checkout/accessory', $accessory->id).'" style="margin-right:5px;" class="btn btn-info btn-sm" '.(($accessory->numRemaining() > 0 ) ? '' : ' disabled').'>'.Lang::get('general.checkout').'</a><a href="'.route('update/accessory', $accessory->id).'" class="btn btn-warning btn-sm" style="margin-right:5px;"><i class="fa fa-pencil icon-white"></i></a><a data-html="false" class="btn delete-asset btn-danger btn-sm" data-toggle="modal" href="'.route('delete/accessory', $accessory->id).'" data-content="'.Lang::get('admin/accessories/message.delete.confirm').'" data-title="'.Lang::get('general.delete').' '.htmlspecialchars($accessory->name).'?" onClick="return false;"><i class="fa fa-trash icon-white"></i></a></nobr>';
            $company = $accessory->company;

            $rows[] = array(
            'name'          => '<a href="'.url('admin/accessories/'.$accessory->id).'/view">'. $accessory->name.'</a>',
            'category'      => ($accessory->category) ? (string)link_to('admin/settings/categories/'.$accessory->category->id.'/view', $accessory->category->name) : '',
            'qty'           => $accessory->qty,
            'order_number'  => $accessory->order_number,
            'min_amt'  => $accessory->min_amt,
            'location'      => ($accessory->location) ? $accessory->location->name: '',
            'purchase_date' => $accessory->purchase_date,
            'purchase_cost' => number_format($accessory->purchase_cost, 2),
            'numRemaining'  => $accessory->numRemaining(),
            'actions'       => $actions,
            'companyName'   => is_null($company) ? '' : e($company->name)
            );
        }

        $data = array('total'=>$accessCount, 'rows'=>$rows);

        return $data;
    }


  /**
  * Generates the JSON response for accessory detail view.
  *
  * Example:
  * <code>
  * {
  * "rows": [
  * {
  * "actions": "(link to available actions)",
  * "name": "(link to user)"
  * }
  * ],
  * "total": 1
  * }
  * </code>
  *
  * The names of the fields in the returns JSON correspond directly to the the
  * names of the fields in the bootstrap-tables in the view.
  *
  * For debugging, see at /api/accessories/$accessoryID/view
  *
  * @author [A. Gianotto] [<snipe@snipe.net>]
  * @param  int  $accessoryId
  * @return string JSON containing accessories and their associated atrributes.
  **/
    public function getDataView($accessoryID)
    {
        $accessory = Accessory::find($accessoryID);

        if (!Company::isCurrentUserHasAccess($accessory)) {
            return ['total' => 0, 'rows' => []];
        }

        $accessory_users = $accessory->users;
        $count = $accessory_users->count();

        $rows = array();

        foreach ($accessory_users as $user) {
            $actions = '<a href="'.route('checkin/accessory', $user->pivot->id).'" class="btn btn-info btn-sm">Checkin</a>';

            $rows[] = array(
              'name'          =>(string) link_to('/admin/users/'.$user->id.'/view', $user->fullName()),
              'actions'       => $actions
              );
        }

        $data = array('total'=>$count, 'rows'=>$rows);

        return $data;
    }
}