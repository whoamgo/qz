<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller {
    public function index() {
        $pageTitle = 'Pricing Plan';
        $plans     = Plan::searchable(['name'])->orderBy('id', 'desc')->paginate(getPaginate());
        return view('admin.plan.index', compact('pageTitle', 'plans'));
    }

    public function add($id = 0) {
        if ($id) {
            $pageTitle = 'Update Plan';
            $plan      = Plan::findOrFail($id);
        } else {
            $pageTitle = 'Add Plan';
            $plan      = null;
        }
        return view('admin.plan.add', compact('pageTitle', 'plan'));
    }

    public function store(Request $request, $id = 0) {
        $request->validate([
            'name'              => 'required|string|max:40|unique:plans,name,' . $id,
            'monthly_price'     => 'required|numeric|gt:0',
            'yearly_price'      => 'required|numeric|gt:0',
            'monthly_exam'      => 'required|integer|gt:0',
            'yearly_exam'       => 'required|integer|gt:0',
            'short_description' => 'required|string|max:255',
        ]);

        if ($id) {
            $plan         = Plan::findOrFail($id);
            $notification = 'Plan updated successfully';
        } else {
            $plan         = new Plan();
            $notification = 'Plan added successfully';
        }

        $plan->name              = $request->name;
        $plan->monthly_price     = $request->monthly_price;
        $plan->yearly_price      = $request->yearly_price;
        $plan->monthly_exam      = $request->monthly_exam;
        $plan->yearly_exam       = $request->yearly_exam;
        $plan->short_description = $request->short_description;
        $plan->save();

        $notify[] = ['success', $notification];
        return back()->withNotify($notify);
    }
    public function status($id) {
        return Plan::changeStatus($id);
    }
}
