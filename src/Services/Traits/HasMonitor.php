<?php

namespace JackedPhp\JackedServer\Services\Traits;

use Carbon\Carbon;
use JackedPhp\JackedServer\Data\RequestNotificationData;
use OpenSwoole\Http\Request;

trait HasMonitor
{
    protected function notifyRequestToMonitor(Request $request): void
    {
        $this->report('[' . Carbon::now()->format('Y-m-d H:i:s') . '] ['
            . $request->server['request_method'] . '] '
            . $request->server['request_uri']);

        if ($request->server['request_uri'] === '/conveyor/message') {
            return;
        }

        if (!$this->auditEnabled) {
            return;
        }

        $this->sendWsMessage(
            channel: MONITOR_CHANNEL, // @phpstan-ignore-line
            message: RequestNotificationData::from([
                'method' => $request->server['request_method'],
                'uri' => $request->server['request_uri'],
                'headers' => $request->header,
                'body' => $request->rawContent(),
            ])->toJson(),
        );
    }
}
