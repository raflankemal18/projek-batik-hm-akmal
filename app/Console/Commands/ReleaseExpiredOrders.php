<?php

namespace App\Console\Commands;

use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Services\AuditLogService;
use App\Services\InventoryManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReleaseExpiredOrders extends Command
{
    protected $signature = 'orders:release-expired';

    protected $description = 'Restore stock and cancel draft orders that have expired';

    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private InventoryManager $inventoryManager,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        DB::transaction(function () {
            $expired = $this->orderRepository->getExpiredDraftsForUpdate();

            foreach ($expired as $order) {
                foreach ($order->details as $detail) {
                    if ($detail->product_size_id) {
                        $this->inventoryManager->restore($detail->product_size_id, $detail->quantity, 'draft_expired');
                    }
                }
                $order->update(['status' => 'cancelled']);

                AuditLogService::recordTransaction('draft.expired', $order, 'Draft order kadaluarsa', [
                    'order_id' => $order->id,
                ]);
            }
        });

        $this->info('Expired draft orders released.');

        return self::SUCCESS;
    }
}
