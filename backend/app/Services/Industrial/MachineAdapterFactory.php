<?php

namespace App\Services\Industrial;

use App\Contracts\Industrial\MachineInterface;
use App\Services\Industrial\Adapters\ModbusMachineAdapter;
use App\Services\Industrial\Adapters\MqttMachineAdapter;
use App\Services\Industrial\Adapters\OpcUaMachineAdapter;
use Exception;

class MachineAdapterFactory
{
    /**
     * Create a machine adapter based on the protocol.
     *
     * @param string $protocol
     * @param array $config
     * @return MachineInterface
     * @throws Exception
     */
    public function make(string $protocol, array $config): MachineInterface
    {
        return match (strtolower($protocol)) {
            'modbus' => new ModbusMachineAdapter($config),
            'mqtt'   => new MqttMachineAdapter($config),
            'opc-ua' => new OpcUaMachineAdapter($config),
            default  => throw new Exception("Unsupported machine protocol: {$protocol}"),
        };
    }
}
