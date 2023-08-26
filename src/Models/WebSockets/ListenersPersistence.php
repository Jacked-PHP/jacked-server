<?php

namespace JackedPhp\JackedServer\Models\WebSockets;

use Conveyor\Models\Interfaces\ListenerPersistenceInterface;
use Error;
use Exception;
use JackedPhp\JackedServer\Models\WsListener;

class ListenersPersistence implements ListenerPersistenceInterface
{
    public function listen(int $fd, string $action): void
    {
        try {
            WsListener::create([
                'fd' => $fd,
                'action' => $action,
            ]);
        } catch (Exception|Error $e) {
            // --
        }
    }

    public function getListener(int $fd): ?array
    {
        return WsListener::where('fd', '=', $fd)->first()?->toArray();
    }

    /**
     * @return array Format: [fd => [listener1, listener2, ...]]
     */
    public function getAllListeners(): array
    {
        try {
            $listeners = WsListener::all()->toArray();
        } catch (Exception|Error $e) {
            return [];
        }

        if (empty($listeners)) {
            return [];
        }

        $listenersArray = [];
        foreach ($listeners as $listener) {
            if (!isset($listenersArray[$listener['fd']])) {
                $listenersArray[$listener['fd']] = [];
            }

            if (!in_array($listener['action'], $listenersArray[$listener['fd']])) {
                $listenersArray[$listener['fd']][] = $listener['action'];
            }
        }

        return $listenersArray;
    }

    public function stopListener(int $fd, string $action): bool
    {
        try {
            return WsListener::where('fd', '=', $fd)
                ->where('action', '=', $action)
                ->first()
                ?->delete();
        } catch (Exception|Error $e) {
            // --
        }

        return false;
    }

    public function stopListenersForFd(int $fd): bool
    {
        try {
            return WsListener::where('fd', '=', $fd)
                ->first()
                ?->delete();
        } catch (Exception|Error $e) {
            // --
        }

        return false;
    }

    public function cleanListeners(): bool
    {
        try {
            return WsListener::all()->delete();
        } catch (Exception|Error $e) {
            // --
        }

        return false;
    }

    public function refresh(bool $fresh = false): static
    {
        if (!$fresh) {
            return $this;
        }

        WsListener::truncate();
        return $this;
    }
}
