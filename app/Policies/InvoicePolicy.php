<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Every user sees and changes only their own invoices.
 * Editing and deleting stop Invoice::LOCK_AFTER_DAYS days after the invoice was created.
 */
class InvoicePolicy
{
    /** Owner, or a super admin (read-only: update/delete below stay owner-only). */
    public function view(User $user, Invoice $invoice): bool
    {
        return $invoice->user_id === $user->id || $user->isSuperAdmin();
    }

    public function update(User $user, Invoice $invoice): Response
    {
        return $this->changeable($user, $invoice, 'edited');
    }

    public function delete(User $user, Invoice $invoice): Response
    {
        return $this->changeable($user, $invoice, 'deleted');
    }

    private function changeable(User $user, Invoice $invoice, string $verb): Response
    {
        if ($invoice->user_id !== $user->id) {
            return Response::deny();
        }

        if ($invoice->isLocked()) {
            return Response::deny(sprintf(
                'Invoice %s is locked. Invoices can only be %s within %d days of being created (until %s).',
                $invoice->number, $verb, Invoice::LOCK_AFTER_DAYS, $invoice->locksAt()->format('d M Y, g:i A'),
            ));
        }

        return Response::allow();
    }
}
