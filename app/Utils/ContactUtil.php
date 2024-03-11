<?php

namespace App\Utils;
use Carbon\Carbon;
use App\Contact;
use App\Utils\TransactionUtil;
use App\Transaction;
use DB;
use Illuminate\Support\Facades\Auth;

class ContactUtil extends Util
{

    
    
    
    
    
    
    public function getWalkInCustomer($business_id, $array = true)
    {
        $contact = Contact::whereIn('type', ['customer', 'both'])
                    ->where('contacts.business_id', $business_id)
                    ->where('contacts.is_default', 1)
                    ->leftjoin('customer_groups as cg', 'cg.id', '=', 'contacts.customer_group_id')
                    ->select('contacts.*', 
                        'cg.amount as discount_percent',
                        'cg.price_calculation_type',
                        'cg.selling_price_group_id'
                    )
                    ->first();

        if (!empty($contact)) {
            $contact->contact_address = $contact->contact_address;
            $output = $array ? $contact->toArray() : $contact;
            return $output;
        } else {
            return null;
        }
    }

    
    
    
    
    public function getCustomerGroup($business_id, $customer_id)
    {
        $cg = [];

        if (empty($customer_id)) {
            return $cg;
        }

        $contact = Contact::leftjoin('customer_groups as CG', 'contacts.customer_group_id', 'CG.id')
            ->where('contacts.id', $customer_id)
            ->where('contacts.business_id', $business_id)
            ->select('CG.*')
            ->first();

        return $contact;
    }

    /**
     * Returns the contact info
     *
     * @param int $business_id
     * @param int $contact_id
     *
     * @return array
     */
    public function getContactInfo($business_id, $contact_id)
    {
        $contact = Contact::where('contacts.id', $contact_id)
                    ->where('contacts.business_id', $business_id)
                    ->leftjoin('transactions AS t', 'contacts.id', '=', 't.contact_id')
                    ->with(['business'])
                    ->select(
                        DB::raw("SUM(IF(t.type = 'purchase', final_total, 0)) as total_purchase"),
                        DB::raw("SUM(IF(t.type = 'sell' AND t.status = 'final', final_total, 0)) as total_invoice"),
                        DB::raw("SUM(IF(t.type = 'purchase', (SELECT SUM(amount) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as purchase_paid"),
                        DB::raw("SUM(IF(t.type = 'sell' AND t.status = 'final', (SELECT SUM(IF(is_return = 1,-1*amount,amount)) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as invoice_received"),
                        DB::raw("SUM(IF(t.type = 'opening_balance', final_total, 0)) as opening_balance"),
                        DB::raw("SUM(IF(t.type = 'opening_balance', (SELECT SUM(amount) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as opening_balance_paid"),
                        'contacts.*'
                    )->first();

        return $contact;
    }

    public function createNewContact($input)
    {
        //Check Contact id
        $count = 0;
        if (!empty($input['contact_id'])) {
            $count = Contact::where('business_id', $input['business_id'])
                            ->where('contact_id', $input['contact_id'])
                            ->count();
        }
        
        if ($count == 0) {
            //Update reference count
            $ref_count = $this->setAndGetReferenceCount('contacts', $input['business_id']);

            if (empty($input['contact_id'])) {
                //Generate reference number
                $input['contact_id'] = $this->generateReferenceNumber('contacts', $ref_count, $input['business_id']);
            }

            $opening_balance = isset($input['opening_balance']) ? $input['opening_balance'] : 0;
            if (isset($input['opening_balance'])) {
                unset($input['opening_balance']);
            }

            //Assigned the user
           
            
            $contact = Contact::create($input);

            
            
            
            
          /*
            
            // my code start
            
            
                    $rp_limit = DB::table('business')->first()->pv_limit;
                    $current_date = Carbon::now();


                   

                    $refered_id2 = DB::table('contacts')->where('referel_id', '=', $contact->refered_id)->get()[0];

                    if ($refered_id2->referel_id != null) {
                        $refer_percent = DB::table('refer_percent')->where('id', '=', 1)->first()->percent;
                        $refered_id2_bonus = ($rp_limit * $refer_percent) / 100;
                        $refered_id2_bonus_rp = $refered_id2->bonus_rp + $refered_id2_bonus;


                        DB::table('contacts')->where('id', '=', $refered_id2->id)->update(['bonus_rp' => $refered_id2_bonus_rp]);

                        DB::table('bonus_info')->insert(['contact_id' => $refered_id2->id, 'bonus_name' => 'RE- Creating Bonus', 'bonus_type' => 'ip_active', 'percent' => $refer_percent, 'bonus_value' => $refered_id2_bonus, 'getting_time' => $current_date]);

                        $refered_id3 = DB::table('contacts')->where('referel_id', '=', $refered_id2->refered_id)->get()[0];

                        if ($refered_id3->referel_id != null) {
                            $refer_percent = DB::table('refer_percent')->where('id', '=', 2)->first()->percent;

                            $refered_id3_bonus = ($rp_limit * $refer_percent) / 100;
                            $refered_id3_bonus_rp = $refered_id3->bonus_rp + $refered_id3_bonus;


                            DB::table('contacts')->where('id', '=', $refered_id3->id)->update(['bonus_rp' => $refered_id3_bonus_rp]);

                            DB::table('bonus_info')->insert(['contact_id' => $refered_id3->id, 'bonus_name' => 'RE- Creating Bonus', 'bonus_type' => 'ip_active', 'percent' => $refer_percent, 'bonus_value' => $refered_id3_bonus, 'getting_time' => $current_date]);

                            $refered_id4 = DB::table('contacts')->where('referel_id', '=', $refered_id3->refered_id)->get()[0];
                            if ($refered_id4->referel_id != null) {
                                $refer_percent = DB::table('refer_percent')->where('id', '=', 3)->first()->percent;

                                $refered_id4_bonus = ($rp_limit * $refer_percent) / 100;
                                $refered_id4_bonus_rp = $refered_id4->bonus_rp + $refered_id4_bonus;


                                DB::table('contacts')->where('id', '=', $refered_id4->id)->update(['bonus_rp' => $refered_id4_bonus_rp]);

                                DB::table('bonus_info')->insert(['contact_id' => $refered_id4->id, 'bonus_name' => 'RE- Creating Bonus', 'bonus_type' => 'ip_active', 'percent' => $refer_percent, 'bonus_value' => $refered_id4_bonus, 'getting_time' => $current_date]);

                                $refered_id5 = DB::table('contacts')->where('referel_id', '=', $refered_id4->refered_id)->get()[0];


                                if ($refered_id5->referel_id != null) {
                                    $refer_percent = DB::table('refer_percent')->where('id', '=', 4)->first()->percent;

                                    $refered_id5_bonus = ($rp_limit * $refer_percent) / 100;
                                    $refered_id5_bonus_rp = $refered_id5->bonus_rp + $refered_id5_bonus;


                                    DB::table('contacts')->where('id', '=', $refered_id5->id)->update(['bonus_rp' => $refered_id5_bonus_rp]);

                                    DB::table('bonus_info')->insert(['contact_id' => $refered_id5->id, 'bonus_name' => 'RE- Creating Bonus', 'bonus_type' => 'ip_active', 'percent' => $refer_percent, 'bonus_value' => $refered_id5_bonus, 'getting_time' => $current_date]);

                                    $refered_id6 = DB::table('contacts')->where('referel_id', '=', $refered_id5->refered_id)->get()[0];

                                    if ($refered_id6->referel_id != null) {
                                        $refer_percent = DB::table('refer_percent')->where('id', '=', 5)->first()->percent;

                                        $refered_id6_bonus = ($rp_limit * $refer_percent) / 100;
                                        $refered_id6_bonus_rp = $refered_id6->bonus_rp + $refered_id6_bonus;


                                        DB::table('contacts')->where('id', '=', $refered_id6->id)->update(['bonus_rp' => $refered_id6_bonus_rp]);

                                        DB::table('bonus_info')->insert(['contact_id' => $refered_id6->id, 'bonus_name' => 'RE- Creating Bonus', 'bonus_type' => 'ip_active', 'percent' => $refer_percent, 'bonus_value' => $refered_id6_bonus, 'getting_time' => $current_date]);

                                        $refered_id7 = DB::table('contacts')->where('referel_id', '=', $refered_id6->refered_id)->get()[0];

                                        if ($refered_id7->referel_id != null) {
                                            $refer_percent = DB::table('refer_percent')->where('id', '=', 6)->first()->percent;

                                            $refered_id7_bonus = ($rp_limit * $refer_percent) / 100;
                                            $refered_id7_bonus_rp = $refered_id7->bonus_rp + $refered_id7_bonus;


                                            DB::table('contacts')->where('id', '=', $refered_id7->id)->update(['bonus_rp' => $refered_id7_bonus_rp]);

                                            DB::table('bonus_info')->insert(['contact_id' => $refered_id7->id, 'bonus_name' => 'RE- Creating Bonus', 'bonus_type' => 'ip_active', 'percent' => $refer_percent, 'bonus_value' => $refered_id7_bonus, 'getting_time' => $current_date]);

                                            $refered_id8 = DB::table('contacts')->where('referel_id', '=', $refered_id7->refered_id)->get()[0];

                                            if ($refered_id8->referel_id != null) {
                                                $refer_percent = DB::table('refer_percent')->where('id', '=', 7)->first()->percent;

                                                $refered_id8_bonus = ($rp_limit * $refer_percent) / 100;
                                                $refered_id8_bonus_rp = $refered_id8->bonus_rp + $refered_id8_bonus;


                                                DB::table('contacts')->where('id', '=', $refered_id8->id)->update(['bonus_rp' => $refered_id8_bonus_rp]);

                                                DB::table('bonus_info')->insert(['contact_id' => $refered_id8->id, 'bonus_name' => 'RE- Creating Bonus', 'bonus_type' => 'ip_active', 'percent' => $refer_percent, 'bonus_value' => $refered_id8_bonus, 'getting_time' => $current_date]);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                        //equel bonus
                        $refered_idEquelBonus1 = DB::table('contacts')->where('referel_id', '=', $contact->refered_id)->first();
                        if($refered_idEquelBonus1->referel_id != null){
                            $equelBonus1=( $rp_limit * 5 ) /100;
                            DB::table('bonus_info')->insert([
                                                            'contact_id' => $refered_idEquelBonus1->id, 
                                                            'bonus_name' => 'Equel Bonus', 
                                                            'bonus_type' => 'ip_active', 
                                                            'percent' => 5, 
                                                            'bonus_value' => $equelBonus1, 
                                                            'getting_time' => $current_date
                                                            ]);
                        }
                        
                        $refered_idEquelBonus2 = DB::table('contacts')->where('referel_id', '=', $refered_idEquelBonus1->refered_id)->first();
                        if($refered_idEquelBonus2->referel_id != null){
                            $equelBonus2=( $rp_limit * 5 ) /100;
                            DB::table('bonus_info')->insert([
                                                            'contact_id' => $refered_idEquelBonus2->id, 
                                                            'bonus_name' => 'Equel Bonus', 
                                                            'bonus_type' => 'ip_active', 
                                                            'percent' => 5, 
                                                            'bonus_value' => $equelBonus2, 
                                                            'getting_time' => $current_date
                                                            ]);
                        }
                        $refered_idEquelBonus3 = DB::table('contacts')->where('referel_id', '=', $refered_idEquelBonus2->refered_id)->first();
                        if($refered_idEquelBonus3->referel_id != null){
                            $equelBonus3=( $rp_limit * 5 ) /100;
                            DB::table('bonus_info')->insert([
                                                            'contact_id' => $refered_idEquelBonus3->id, 
                                                            'bonus_name' => 'Equel Bonus', 
                                                            'bonus_type' => 'ip_active', 
                                                            'percent' => 5, 
                                                            'bonus_value' => $equelBonus3, 
                                                            'getting_time' => $current_date
                                                            ]);
                        }
                        $refered_idEquelBonus4 = DB::table('contacts')->where('referel_id', '=', $refered_idEquelBonus3->refered_id)->first();
                        if($refered_idEquelBonus4->referel_id != null){
                            $equelBonus4=( $rp_limit * 5 ) /100;
                            DB::table('bonus_info')->insert([
                                                            'contact_id' => $refered_idEquelBonus4->id, 
                                                            'bonus_name' => 'Equel Bonus', 
                                                            'bonus_type' => 'ip_active', 
                                                            'percent' => 5, 
                                                            'bonus_value' => $equelBonus4, 
                                                            'getting_time' => $current_date
                                                            ]);
                        }
                        $refered_idEquelBonus5 = DB::table('contacts')->where('referel_id', '=', $refered_idEquelBonus4->refered_id)->first();
                        if($refered_idEquelBonus5->referel_id != null){
                            $equelBonus5=( $rp_limit * 5 ) /100;
                            DB::table('bonus_info')->insert([
                                                            'contact_id' => $refered_idEquelBonus5->id, 
                                                            'bonus_name' => 'Equel Bonus', 
                                                            'bonus_type' => 'ip_active', 
                                                            'percent' => 5, 
                                                            'bonus_value' => $equelBonus5, 
                                                            'getting_time' => $current_date
                                                            ]);
                        }
                    
                    //admin company profit share bonus//
                    $company_profit_percent = DB::table('ip_active')->first()->company_profit_share;
                    $company_profit_points = ($rp_limit * intval($company_profit_percent)) / 100;
                    $company_profit_point = DB::table('users')->where('id', '=', auth()->user()->id)->first()->company_profit_share + $company_profit_points;

                    DB::table('users')->where('id', '=', auth()->user()->id)->update(['company_profit_share' => $company_profit_point]);

                    DB::table('bonus_info')->insert(['contact_id' => $contact->id, 'bonus_type' => 'ip_active', 'percent' => intval($company_profit_percent), 'bonus_name' => 'Company Profit Share', 'bonus_value' => $company_profit_points, 'role' => 'admin', 'getting_time' => $current_date]);



                    //admin rank reward point//
                    $after_death_percent = DB::table('ip_active')->first()->after_death_allowance;
                    $after_death_points = ($rp_limit * intval($after_death_percent)) / 100;
                    $after_death_point = DB::table('users')->where('id', '=', auth()->user()->id)->first()->after_death_allowance + $after_death_points;

                    DB::table('users')->where('id', '=', auth()->user()->id)->update(['after_death_allowance' => $after_death_point]);

                    DB::table('bonus_info')->insert(['contact_id' => $contact->id, 'bonus_type' => 'ip_active', 'percent' => intval($after_death_percent), 'bonus_name' => 'After Death Allowance', 'bonus_value' => $after_death_points, 'role' => 'admin', 'getting_time' => $current_date]);



                    //admin rank reward point//
                    $rank_reward_percent = DB::table('ip_active')->first()->rank_reward_bonus;
                    $rank_reward_points = ($rp_limit * intval($rank_reward_percent)) / 100;
                    $rank_reward_point = DB::table('users')->where('id', '=', auth()->user()->id)->first()->rank_reward_point + $rank_reward_points;
                    DB::table('users')->where('id', '=', auth()->user()->id)->update(['rank_reward_point' => $rank_reward_point]);
                    DB::table('bonus_info')->insert(['contact_id' => $contact->id, 'bonus_type' => 'ip_active', 'percent' => intval($rank_reward_percent), 'bonus_name' => 'Rank Reward Bonus', 'bonus_value' => $rank_reward_points, 'role' => 'admin', 'getting_time' => $current_date]);



                    //Creating Bonus (referel User)
                    $creating_percent = DB::table('ip_active')->first()->creating_bonus;
                    $refer_bonus = $rp_limit / intval($creating_percent);

                    DB::table('bonus_info')->insert(['contact_id' => $refered_id2->id, 'bonus_type' => 'ip_active', 'percent' => intval($creating_percent), 'bonus_name' => 'Creating Bonus', 'bonus_value' => $refer_bonus, 'getting_time' => $current_date]);



                    //Purchase Bonus (user)
                    // $purchase_percent = DB::table('ip_active')->first()->purchase_bonus;
                    // $own_bonus = $rp_limit / intval($purchase_percent);
                    // $own_bonus_rp = $own_bonus + $v->bonus_rp;

                    // DB::table('bonus_info')->insert(['contact_id' => $contact->id, 'bonus_type' => 'ip_active', 'percent' => intval($purchase_percent), 'bonus_name' => 'Purchase Bonus', 'bonus_value' => $own_bonus, 'getting_time' => $current_date]);



                    //Gourdianship Bonus (referel user)//
                    $guardianship_bonus = DB::table('ip_active')->first()->guardianship_bonus;
                    $guardianship_points = ($rp_limit * intval($guardianship_bonus)) / 100;

                    DB::table('bonus_info')->insert(['contact_id' => $refered_id2->id, 'bonus_type' => 'ip_active', 'percent' => intval($guardianship_bonus), 'bonus_name' => 'Guardianship Bonus', 'bonus_value' => $guardianship_points, 'paid' => '0', 'getting_time' => $current_date]);



                    //Captainship Bonus (user)//
                    $championship_bonus = DB::table('ip_active')->first()->championship_bonus;
                    $championship_points = ($rp_limit * intval($championship_bonus)) / 100;

                    DB::table('bonus_info')->insert(['contact_id' => $contact->id, 'bonus_type' => 'ip_active', 'percent' => intval($championship_bonus), 'bonus_name' => 'Captainship Bonus', 'bonus_value' => $championship_points, 'paid' => '0', 'getting_time' => $current_date]);



                    //user company profit share point (user)
                    DB::table('bonus_info')->insert(['contact_id' => $contact->id, 'bonus_type' => 'ip_active', 'percent' => intval($company_profit_percent), 'bonus_name' => 'Company Profit Share', 'bonus_value' => $company_profit_points, 'getting_time' => $current_date]);


                 

                    

                    //referer bonus (referel user)
                    $refer_bonus_rp = $refer_bonus + $refered_id1->bonus_rp;
                    DB::table('contacts')->where('referel_id', '=', $contact->refer_id)->update(['bonus_rp' => $refer_bonus_rp]);



            
            //mycode end
            
            */
            
            
            //Assigned the user
            if(!empty($assigned_to_users)){
                $contact->userHavingAccess()->sync($assigned_to_users);
            }

            //Add opening balance
            if (!empty($opening_balance)) {
                $transactionUtil = new TransactionUtil();
                $transactionUtil->createOpeningBalanceTransaction($contact->business_id, $contact->id, $opening_balance, $contact->created_by, false);
            }

            $output = ['success' => true,
                        'data' => $contact,
                        'msg' => __("contact.added_success")
                    ];
            return $output;
        } else {
            throw new \Exception("Error Processing Request", 1);
        }
    }

    public function updateContact($input, $id, $business_id)
    {
        $count = 0;
        //Check Contact id
        if (!empty($input['contact_id'])) {
            $count = Contact::where('business_id', $business_id)
                    ->where('contact_id', $input['contact_id'])
                    ->where('id', '!=', $id)
                    ->count();
        }

        if ($count == 0) {
            //Get opening balance if exists
            $ob_transaction =  Transaction::where('contact_id', $id)
                                    ->where('type', 'opening_balance')
                                    ->first();

            $opening_balance = isset($input['opening_balance']) ? $input['opening_balance'] : 0;
            if (isset($input['opening_balance'])) {
                unset($input['opening_balance']);
            }

            //Assigned the user
            $assigned_to_users = [];;
            if(!empty($input['assigned_to_users'])){
                $assigned_to_users = $input['assigned_to_users'];
                unset($input['assigned_to_users']);
            }
            
            $contact = Contact::where('business_id', $business_id)->findOrFail($id);
            foreach ($input as $key => $value) {
                $contact->$key = $value;
            }
            $contact->save();


            //Assigned the user
            if(!empty($assigned_to_users)){
                $contact->userHavingAccess()->sync($assigned_to_users);
            }
            
            //Opening balance update
            $transactionUtil = new TransactionUtil();
            if (!empty($ob_transaction)) {
                $opening_balance_paid = $transactionUtil->getTotalAmountPaid($ob_transaction->id);
                if (!empty($opening_balance_paid)) {
                    $opening_balance += $opening_balance_paid;
                }
                
                $ob_transaction->final_total = $opening_balance;
                $ob_transaction->save();
                //Update opening balance payment status
                $transactionUtil->updatePaymentStatus($ob_transaction->id, $ob_transaction->final_total);
            } else {
                //Add opening balance
                if (!empty($opening_balance)) {
                    $transactionUtil->createOpeningBalanceTransaction($business_id, $contact->id, $opening_balance, $contact->created_by, false);
                }
            }

            $output = ['success' => true,
                        'msg' => __("contact.updated_success"),
                        'data' => $contact
                        ];
        } else {
            throw new \Exception("Error Processing Request", 1);
        }

        return $output;
    }
    
    public function getContactQuery($business_id, $type, $contact_ids = [])
    {
        $query = Contact::leftjoin('transactions AS t', 'contacts.id', '=', 't.contact_id')
                    ->leftjoin('customer_groups AS cg', 'contacts.customer_group_id', '=', 'cg.id')
                    ->where('contacts.business_id', $business_id);

        if ($type == 'supplier') {
           $query->onlySuppliers();
        } elseif ($type == 'customer') {
            $query->onlyCustomers();
        } else {
            if (auth()->check() && ( (!auth()->user()->can('customer.view') && auth()->user()->can('customer.view_own'))) || (!auth()->user()->can('supplier.view') && auth()->user()->can('supplier.view_own')) ) {
                $query->onlyOwnContact();
            }
        }
        if (!empty($contact_ids)) {
            $query->whereIn('contacts.id', $contact_ids);
        }

        $query->select([
            'contacts.*', 
            'cg.name as customer_group',
            DB::raw("SUM(IF(t.type = 'opening_balance', final_total, 0)) as opening_balance"),
            DB::raw("SUM(IF(t.type = 'opening_balance', (SELECT SUM(IF(is_return = 1,-1*amount,amount)) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as opening_balance_paid"),
            DB::raw("MAX(DATE(transaction_date)) as max_transaction_date"),
            DB::raw("SUM(IF(t.type = 'ledger_discount', final_total, 0)) as total_ledger_discount"),
            't.transaction_date'
        ]);

        if (in_array($type, ['supplier', 'both'])) {
            $query->addSelect([
                DB::raw("SUM(IF(t.type = 'purchase', final_total, 0)) as total_purchase"),
                DB::raw("SUM(IF(t.type = 'purchase', (SELECT SUM(amount) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as purchase_paid"),
                DB::raw("SUM(IF(t.type = 'purchase_return', final_total, 0)) as total_purchase_return"),
                DB::raw("SUM(IF(t.type = 'purchase_return', (SELECT SUM(amount) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as purchase_return_paid")
            ]);
        }

        if (in_array($type, ['customer', 'both'])) {
            $query->addSelect([
                DB::raw("SUM(IF(t.type = 'sell' AND t.status = 'final', final_total, 0)) as total_invoice"),
                DB::raw("SUM(IF(t.type = 'sell' AND t.status = 'final', (SELECT SUM(IF(is_return = 1,-1*amount,amount)) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as invoice_received"),
                DB::raw("SUM(IF(t.type = 'sell_return', final_total, 0)) as total_sell_return"),
                DB::raw("SUM(IF(t.type = 'sell_return', (SELECT SUM(amount) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as sell_return_paid")
            ]);
        } 
        $query->groupBy('contacts.id');

        return $query;
    }
}
