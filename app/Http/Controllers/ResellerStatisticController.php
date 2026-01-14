<?php

namespace App\Http\Controllers;

use App\ResellerStatistic;
use App\User;
use App\SubResiler;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

use DB;
use Auth;

class ResellerStatisticController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        if ($user_type == 'Admin') {
            $get_all = ResellerStatistic::orderBy('created_at', 'desc')->paginate(20);
            $data = [];
            foreach ($get_all as $item) {

                if ($item->operation_name == 'user') {
                    if ($item->slug == '' || $item->slug == null) {
                        $item->description = 'Add or renew user';
                    } else {
                        if ($item->slug == 'create') {
                            $item->description = 'Add user';
                        } else if ($item->slug == 'renew') {
                            $item->description = 'Renew a user';
                        } else {
                            $item->description = 'Add or renew user';
                        }
                    }
                } else {
                    if ($item->operation_name == 'active_code') {
                        if ($item->slug == '' || $item->slug == null) {
                            $item->description = 'Add or renew an active code';
                        } else {
                            if ($item->slug == 'create') {
                                $item->description = 'Add active code';
                            } else if ($item->slug == 'renew') {
                                $item->description = 'Renew an active code';
                            } else {
                                $item->description = 'Add or renew an active code';
                            }
                        }
                    } else {
                        if ($item->operation_name == 'mass_code') {
                            if ($item->slug == '' || $item->slug == null) {
                                $item->description = 'Add or renew a mass code';
                            } else {
                                if ($item->slug == 'create') {
                                    $item->description = 'Add mass code';
                                } else if ($item->slug == 'renew') {
                                    $item->description = 'Renew a mass code';
                                } else {
                                    $item->description = 'Add or renew a mass code';
                                }
                            }
                        } else {
                            if ($item->operation_name == 'mag_device') {
                                if ($item->slug == '' || $item->slug == null) {
                                    $item->description = 'Add or renew a mag device';
                                } else {
                                    if ($item->slug == 'create') {
                                        $item->description = 'Add mag device';
                                    } else if ($item->slug == 'renew') {
                                        $item->description = 'Renew a mag device';
                                    } else {
                                        $item->description = 'Add or renew a mag device';
                                    }
                                }
                            } else {
                                if ($item->operation_name == 'add_credit_to_sub_reseller') {
                                    if ($item->slug == '' || $item->slug == null) {
                                        $item->description = 'Add credit to sub reseller';
                                    } else {
                                        $item->description = $item->slug;
                                    }

                                } else {
                                    if ($item->operation_name == 'add_credit_to_reseller') {
                                        $item->description = $item->slug;
                                    } else if ($item->operation_name == 'recover_credit_to_sub_reseller') {
                                        $item->description = $item->slug;
                                    } else if ($item->operation_name == 'destroy_reseller') {
                                        $item->description = $item->slug;
                                    }
                                }
                            }
                        }
                    }
                }
                $item->solde = $item->solde;
                $item->operation = $item->operation;
                $st = strtotime($item->created_at);
                $item->date = date('Y-m-d', $st);
                $item->id = $item->id;
                $res = User::find($item->reseller_id);
                if ($res) {
                    $item->reseller = $res->name;
                } else {
                    $item->reseller = '';
                }

            }

            return response()->json($get_all);

        } else {
            $subRes = SubResiler::where('res_id', $user_id)->get();
            $subArray = [];
            foreach ($subRes as $row) {
                array_push($subArray, $row->user_id);
            }
            array_push($subArray, $user_id);

            $get_all = ResellerStatistic::whereIn('reseller_id', $subArray)->orderBy('created_at', 'desc')->get();
            $data = [];
            foreach ($get_all as $item) {
                $row = [];
                if ($item->operation_name == 'user') {
                    if ($item->slug == '' || $item->slug == null) {
                        $row['description'] = 'Add or renew user';
                    } else {
                        if ($item->slug == 'create') {
                            $row['description'] = 'Add user';
                        } else if ($item->slug == 'renew') {
                            $row['description'] = 'Renew a user';
                        } else {
                            $row['description'] = 'Add or renew user';
                        }
                    }
                } else {
                    if ($item->operation_name == 'active_code') {
                        if ($item->slug == '' || $item->slug == null) {
                            $row['description'] = 'Add or renew an active code';
                        } else {
                            if ($item->slug == 'create') {
                                $row['description'] = 'Add active code';
                            } else if ($item->slug == 'renew') {
                                $row['description'] = 'Renew an active code';
                            } else {
                                $row['description'] = 'Add or renew an active code';
                            }
                        }
                    } else {
                        if ($item->operation_name == 'mass_code') {
                            if ($item->slug == '' || $item->slug == null) {
                                $row['description'] = 'Add or renew a mass code';
                            } else {
                                if ($item->slug == 'create') {
                                    $row['description'] = 'Add mass code';
                                } else if ($item->slug == 'renew') {
                                    $row['description'] = 'Renew a mass code';
                                } else {
                                    $row['description'] = 'Add or renew a mass code';
                                }
                            }
                        } else {
                            if ($item->operation_name == 'mag_device') {
                                if ($item->slug == '' || $item->slug == null) {
                                    $row['description'] = 'Add or renew a mag device';
                                } else {
                                    if ($item->slug == 'create') {
                                        $row['description'] = 'Add mag device';
                                    } else if ($item->slug == 'renew') {
                                        $row['description'] = 'Renew a mag device';
                                    } else {
                                        $row['description'] = 'Add or renew a mag device';
                                    }
                                }
                            } else {
                                if ($item->operation_name == 'add_credit_to_sub_reseller') {
                                    if ($item->slug == '' || $item->slug == null) {
                                        $row['description'] = 'Add credit to sub reseller';
                                    } else {
                                        $row['description'] = $item->slug;
                                    }
                                } else {
                                    if ($item->operation_name == 'add_credit_to_reseller') {
                                        $row['description'] = $item->slug;
                                    } else if ($item->operation_name == 'recover_credit_to_sub_reseller') {
                                        $row['description'] = $item->slug;
                                    } else if ($item->operation_name == 'destroy_reseller') {
                                        $row['description'] = $item->slug;
                                    }
                                }
                            }
                        }
                    }
                }
                $row['solde'] = $item->solde;
                $row['operation'] = $item->operation;
                $st = strtotime($item->created_at);
                $row['date'] = date('Y-m-d', $st);
                $row['id'] = $item->id;
                $res = User::find($item->reseller_id);
                $row['reseller'] = $res->name;
                array_push($data, $row);
            }

            $perPage = 20;
            $pageStart = \Request::get('page', 1);
            $offSet = ($pageStart * $perPage) - $perPage;
            $itemsForCurrentPage = array_slice($data, $offSet, $perPage, true);
            return new LengthAwarePaginator($itemsForCurrentPage, count($data), $perPage, Paginator::resolveCurrentPage(), array('path' => Paginator::resolveCurrentPath()));
        }
    }

    public function transactions_by_res(Request $request)
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $request->validate([
            'res_id' => 'required|integer'
        ]);

        $target_res_id = $request->res_id;

        // Authorization Check
        if ($user_type !== 'Admin') {
            // Check if successful target is self
            if ($target_res_id == $user_id) {
                // Allowed
            } else {
                // Check if target is a sub-reseller
                $is_sub = SubResiler::where('res_id', $user_id)->where('user_id', $target_res_id)->exists();
                if (!$is_sub) {
                    return response()->json(['message' => 'Unauthorized'], 403);
                }
            }
        }

        // Fetch Data
        $get_all = ResellerStatistic::where('reseller_id', $target_res_id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Format Data (Logic copied from index)
        foreach ($get_all as $item) {
            if ($item->operation_name == 'user') {
                if ($item->slug == '' || $item->slug == null) {
                    $item->description = 'Add or renew user';
                } else {
                    if ($item->slug == 'create') {
                        $item->description = 'Add user';
                    } else if ($item->slug == 'renew') {
                        $item->description = 'Renew a user';
                    } else {
                        $item->description = 'Add or renew user';
                    }
                }
            } else {
                if ($item->operation_name == 'active_code') {
                    if ($item->slug == '' || $item->slug == null) {
                        $item->description = 'Add or renew an active code';
                    } else {
                        if ($item->slug == 'create') {
                            $item->description = 'Add active code';
                        } else if ($item->slug == 'renew') {
                            $item->description = 'Renew an active code';
                        } else {
                            $item->description = 'Add or renew an active code';
                        }
                    }
                } else {
                    if ($item->operation_name == 'mass_code') {
                        if ($item->slug == '' || $item->slug == null) {
                            $item->description = 'Add or renew a mass code';
                        } else {
                            if ($item->slug == 'create') {
                                $item->description = 'Add mass code';
                            } else if ($item->slug == 'renew') {
                                $item->description = 'Renew a mass code';
                            } else {
                                $item->description = 'Add or renew a mass code';
                            }
                        }
                    } else {
                        if ($item->operation_name == 'mag_device') {
                            if ($item->slug == '' || $item->slug == null) {
                                $item->description = 'Add or renew a mag device';
                            } else {
                                if ($item->slug == 'create') {
                                    $item->description = 'Add mag device';
                                } else if ($item->slug == 'renew') {
                                    $item->description = 'Renew a mag device';
                                } else {
                                    $item->description = 'Add or renew a mag device';
                                }
                            }
                        } else {
                            if ($item->operation_name == 'add_credit_to_sub_reseller') {
                                if ($item->slug == '' || $item->slug == null) {
                                    $item->description = 'Add credit to sub reseller';
                                } else {
                                    $item->description = $item->slug;
                                }
                            } else {
                                if ($item->operation_name == 'add_credit_to_reseller') {
                                    $item->description = $item->slug;
                                } else if ($item->operation_name == 'recover_credit_to_sub_reseller') {
                                    $item->description = $item->slug;
                                } else if ($item->operation_name == 'destroy_reseller') {
                                    $item->description = $item->slug;
                                }
                            }
                        }
                    }
                }
            }
            // item->solde is already set
            // item->operation is already set
            $st = strtotime($item->created_at);
            $item->date = date('Y-m-d', $st);
            // item->id is already set
            $res = User::find($item->reseller_id);
            if ($res) {
                $item->reseller = $res->name;
            } else {
                $item->reseller = '';
            }
        }

        return response()->json($get_all);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\ResellerStatistic  $resellerStatistic
     * @return \Illuminate\Http\Response
     */
    public function show(ResellerStatistic $resellerStatistic)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\ResellerStatistic  $resellerStatistic
     * @return \Illuminate\Http\Response
     */
    public function edit(ResellerStatistic $resellerStatistic)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\ResellerStatistic  $resellerStatistic
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ResellerStatistic $resellerStatistic)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\ResellerStatistic  $resellerStatistic
     * @return \Illuminate\Http\Response
     */
    public function destroy(ResellerStatistic $resellerStatistic)
    {
        //
    }
}
