<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function index()
    {
        $users = User::latest()->get();
        return response()->json(['status' => true, 'data' => $users]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role' => 'required|in:admin,staff',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'User created successfully',
            'data' => $user
        ]);
    }

public function staffPerformance($id)
    {
        try {
            $user = User::findOrFail($id);

            $isOnline = false;
            $lastSeenText = 'Never';

            if ($user->last_seen_at) {
                $lastSeen = Carbon::parse($user->last_seen_at);

                if ($lastSeen->diffInMinutes(now()) < 5) {
                    $isOnline = true;
                }

                $lastSeenText = $lastSeen->diffForHumans();
            }

            $today = date('Y-m-d');
            $thisMonth = date('m');
            $thisYear = date('Y');

            $dailySale = Sale::where('created_by', $id)
                            ->whereDate('date', $today)
                            ->sum('grand_total');

            $monthlySale = Sale::where('created_by', $id)
                            ->whereMonth('date', $thisMonth)
                            ->whereYear('date', $thisYear)
                            ->sum('grand_total');

            $lifetimeSale = Sale::where('created_by', $id)->sum('grand_total');
            $totalInvoices = Sale::where('created_by', $id)->count();
            $totalExpense = Expense::where('created_by', $id)->sum('amount');
            $monthlyExpense = Expense::where('created_by', $id)
                                ->whereMonth('date', $thisMonth)
                                ->sum('amount');

            return response()->json([
                'status' => true,
                'data' => [
                    'profile' => [
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone ?? 'N/A',
                        'address' => $user->address ?? 'N/A',
                        'role' => $user->role,
                        'joined_at' => $user->created_at->format('d M, Y'),

                        'last_seen' => $lastSeenText,
                        'is_online' => $isOnline,
                    ],
                    'sales_performance' => [
                        'daily' => $dailySale,
                        'monthly' => $monthlySale,
                        'lifetime' => $lifetimeSale,
                        'total_invoices' => $totalInvoices
                    ],
                    'expense_report' => [
                        'monthly' => $monthlyExpense,
                        'lifetime' => $totalExpense
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }


    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email,' . $id,
            'phone'    => 'nullable|string|max:20',
            'address'  => 'nullable|string',
            'role'     => 'required|in:admin,staff',
            'password' => 'nullable|min:6',
            'avatar'   => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        try {
            $data = [
                'name'    => $request->name,
                'email'   => $request->email,
                'phone'   => $request->phone,
                'address' => $request->address,
                'role'    => $request->role,
            ];

            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            if ($request->hasFile('avatar')) {
                if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                    Storage::disk('public')->delete($user->avatar);
                }

                $path = $request->file('avatar')->store('avatars', 'public');
                $data['avatar'] = $path;
            }

            $user->update($data);

            return response()->json([
                'status'  => true,
                'message' => 'User updated successfully',
                'data'    => $user
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }


    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json(['status' => false, 'message' => 'You cannot delete yourself!'], 400);
        }

        $user->delete();
        return response()->json(['status' => true, 'message' => 'User deleted successfully']);
    }
}
