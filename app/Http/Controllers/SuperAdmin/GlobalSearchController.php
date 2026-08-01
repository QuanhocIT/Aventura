<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\BillingInvoice;
use App\Models\Restaurant;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        if (strlen($query) < 2) {
            return response()->json([
                'restaurants' => [],
                'users' => [],
                'tickets' => [],
                'invoices' => [],
            ]);
        }

        // Search Restaurants (using database as fallback or Meilisearch)
        try {
            $restaurants = Restaurant::search($query)->take(5)->get();
        } catch (\Throwable $e) {
            $restaurants = Restaurant::where('name', 'like', "%{$query}%")
                ->orWhere('code', 'like', "%{$query}%")
                ->take(5)
                ->get();
        }

        // Search Users (Owners, staff)
        $users = User::where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->take(5)
            ->get();

        // Search Tickets
        $tickets = SupportTicket::where('subject', 'like', "%{$query}%")
            ->orWhere('ticket_number', 'like', "%{$query}%")
            ->take(5)
            ->get();

        // Search Invoices
        $invoices = BillingInvoice::where('invoice_number', 'like', "%{$query}%")
            ->take(5)
            ->get();

        return response()->json([
            'restaurants' => $restaurants->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'code' => $r->code,
                'status' => $r->status,
                'url' => route('superadmin.restaurants.show', $r->id),
            ]),
            'users' => $users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'url' => route('superadmin.accounts.index').'?search='.urlencode($u->email),
            ]),
            'tickets' => $tickets->map(fn ($t) => [
                'id' => $t->id,
                'ticket_number' => $t->ticket_number,
                'subject' => $t->subject,
                'status' => $t->status,
                'url' => route('superadmin.support.index').'?ticket_id='.$t->id,
            ]),
            'invoices' => $invoices->map(fn ($i) => [
                'id' => $i->id,
                'invoice_number' => $i->invoice_number,
                'total' => number_format($i->total),
                'status' => $i->status,
                'url' => route('superadmin.billing.index').'?search='.urlencode($i->invoice_number),
            ]),
        ]);
    }
}
