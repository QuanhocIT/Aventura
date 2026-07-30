<?php

namespace App\Console\Commands;

use App\Services\ServiceMonitorService;
use Illuminate\Console\Command;

class CheckServiceHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'services:check-health';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ping all backend services and microservices to record latency and status';

    /**
     * Execute the console command.
     */
    public function handle(ServiceMonitorService $monitorService)
    {
        $this->info('Starting service health checks...');
        
        $results = $monitorService->checkAll();
        
        foreach ($results as $service => $ping) {
            $this->line(sprintf(
                '[%s] Status: %s, Latency: %sms %s',
                strtoupper($service),
                $ping['status'] === 'online' ? 'ONLINE' : 'OFFLINE',
                $ping['latency'],
                $ping['error'] ? ' - Error: ' . $ping['error'] : ''
            ));
        }
        
        $this->info('Service health checks completed.');
    }
}
