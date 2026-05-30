<?php

namespace App\Services\Machine\Modbus;

use App\Models\MachineTag;
use App\Models\ModbusConnection;
use ModbusTcpClient\Network\BinaryStreamConnection;
use ModbusTcpClient\Packet\ModbusFunction\ReadCoilsRequest;
use ModbusTcpClient\Packet\ModbusFunction\ReadHoldingRegistersRequest;
use ModbusTcpClient\Packet\ModbusFunction\ReadInputDiscretesRequest;
use ModbusTcpClient\Packet\ModbusFunction\ReadInputRegistersRequest;
use ModbusTcpClient\Packet\ResponseFactory;
use ModbusTcpClient\Utils\Endian;

/**
 * Thin wrapper over aldas/modbus-tcp-client. Reads one tag at a time using the
 * request type implied by register_type, and decodes per data_type with the
 * connection's word/byte order. (Batch reads are a later optimization.)
 */
class ModbusReader
{
    private ?BinaryStreamConnection $conn = null;

    public function __construct(private readonly ModbusConnection $modbus) {}

    public function connect(): void
    {
        $this->conn = BinaryStreamConnection::getBuilder()
            ->setHost($this->modbus->host)
            ->setPort($this->modbus->port)
            ->setConnectTimeoutSec((float) $this->modbus->timeout_seconds)
            ->setReadTimeoutSec((float) $this->modbus->timeout_seconds)
            ->build();
        $this->conn->connect();
    }

    public function close(): void
    {
        $this->conn?->close();
        $this->conn = null;
    }

    private function endian(): int
    {
        $byte = $this->modbus->byte_order === 'little' ? Endian::LITTLE_ENDIAN : Endian::BIG_ENDIAN;
        $word = $this->modbus->word_order === 'little' ? Endian::LOW_WORD_FIRST : 0;

        return $byte | $word;
    }

    /**
     * Read a single tag and return its decoded raw value (pre-transform).
     */
    public function readTag(MachineTag $tag): mixed
    {
        $address = (int) $this->normalizeAddress($tag->address);
        $unit = $this->modbus->unit_id;
        $is32 = in_array($tag->data_type, ['int32', 'uint32', 'float32'], true);
        $quantity = $is32 ? 2 : 1;

        $registerType = $tag->register_type ?? 'holding';

        $request = match ($registerType) {
            'coil' => new ReadCoilsRequest($address, 1, $unit),
            'discrete' => new ReadInputDiscretesRequest($address, 1, $unit),
            'input' => new ReadInputRegistersRequest($address, $quantity, $unit),
            default => new ReadHoldingRegistersRequest($address, $quantity, $unit),
        };

        $binary = $this->conn->sendAndReceive($request);
        $response = ResponseFactory::parseResponseOrThrow($binary)->withStartAddress($address);

        if (in_array($registerType, ['coil', 'discrete'], true)) {
            return (bool) $response[$address];
        }

        $endian = $this->endian();

        if ($is32) {
            $dword = $response->getDoubleWordAt($address);

            return match ($tag->data_type) {
                'float32' => $dword->getFloat($endian),
                'uint32' => $dword->getUInt32($endian),
                default => $dword->getInt32($endian),
            };
        }

        $word = $response->getWordAt($address);

        return match ($tag->data_type) {
            'uint16' => $word->getUInt16($endian),
            'bool' => (bool) $word->getUInt16($endian),
            default => $word->getInt16($endian),
        };
    }

    /**
     * Accept either a raw 0-based offset ("5") or a Modicon address
     * ("40006" → 5, "30002" → 1) and return the 0-based register offset.
     */
    private function normalizeAddress(string $address): int
    {
        $address = trim($address);
        if (preg_match('/^[0134]\d{4}$/', $address)) {
            return ((int) substr($address, 1)) - 1;
        }

        return (int) $address;
    }
}
