import { useState } from 'react';
import {
  Container,
  Title,
  Stack,
  Card,
  Table,
  Text,
  Badge,
  Group,
  Select,
  TextInput,
  Button,
  Loader,
  Code,
  Collapse,
  ActionIcon,
} from '@mantine/core';
import { DatePickerInput } from '@mantine/dates';
import { IconChevronDown, IconChevronRight, IconDownload } from '@tabler/icons-react';
import { useAuditLogs, useExportAuditLogs } from '../../hooks/useAuditLogs';

const getActionColor = (action: string) => {
  switch (action) {
    case 'created':
      return 'green';
    case 'updated':
      return 'blue';
    case 'deleted':
      return 'red';
    default:
      return 'gray';
  }
};

export function AuditLogsPage() {
  const [entityType, setEntityType] = useState<string>('');
  const [action, setAction] = useState<string>('');
  const [dateRange, setDateRange] = useState<[Date | null, Date | null]>([null, null]);
  const [expandedRows, setExpandedRows] = useState<Set<number>>(new Set());
  const exportAuditLogs = useExportAuditLogs();

  const { data: auditLogsData, isLoading } = useAuditLogs({
    entity_type: entityType || undefined,
    action: action || undefined,
    start_date: dateRange[0]?.toISOString(),
    end_date: dateRange[1]?.toISOString(),
    per_page: 50,
  });

  const auditLogs = auditLogsData?.data || [];

  const toggleRow = (id: number) => {
    setExpandedRows(prev => {
      const newSet = new Set(prev);
      if (newSet.has(id)) {
        newSet.delete(id);
      } else {
        newSet.add(id);
      }
      return newSet;
    });
  };

  const handleExport = async () => {
    await exportAuditLogs({
      entity_type: entityType || undefined,
      start_date: dateRange[0]?.toISOString(),
      end_date: dateRange[1]?.toISOString(),
    });
  };

  const renderDiff = (beforeState?: Record<string, any>, afterState?: Record<string, any>) => {
    if (!beforeState && !afterState) return null;

    const changes: Array<{ field: string; before: any; after: any }> = [];

    if (afterState) {
      Object.keys(afterState).forEach(key => {
        const before = beforeState?.[key];
        const after = afterState[key];
        if (JSON.stringify(before) !== JSON.stringify(after)) {
          changes.push({ field: key, before, after });
        }
      });
    }

    return (
      <Table size="sm">
        <Table.Thead>
          <Table.Tr>
            <Table.Th>Field</Table.Th>
            <Table.Th>Before</Table.Th>
            <Table.Th>After</Table.Th>
          </Table.Tr>
        </Table.Thead>
        <Table.Tbody>
          {changes.map(({ field, before, after }) => (
            <Table.Tr key={field}>
              <Table.Td><Code>{field}</Code></Table.Td>
              <Table.Td><Code color="red">{JSON.stringify(before)}</Code></Table.Td>
              <Table.Td><Code color="green">{JSON.stringify(after)}</Code></Table.Td>
            </Table.Tr>
          ))}
        </Table.Tbody>
      </Table>
    );
  };

  return (
    <Container size="xl" py="xl">
      <Stack gap="xl">
        <Group justify="space-between">
          <Title order={1}>Audit Logs</Title>
          <Button
            leftSection={<IconDownload size={18} />}
            onClick={handleExport}
            variant="light"
          >
            Export to CSV
          </Button>
        </Group>

        {/* Filters */}
        <Card withBorder padding="md">
          <Group gap="md">
            <Select
              placeholder="All Entities"
              value={entityType}
              onChange={(value) => setEntityType(value || '')}
              data={[
                { value: '', label: 'All Entities' },
                { value: 'WorkOrder', label: 'Work Orders' },
                { value: 'Batch', label: 'Batches' },
                { value: 'Issue', label: 'Issues' },
                { value: 'User', label: 'Users' },
              ]}
              style={{ width: 200 }}
              clearable
            />

            <Select
              placeholder="All Actions"
              value={action}
              onChange={(value) => setAction(value || '')}
              data={[
                { value: '', label: 'All Actions' },
                { value: 'created', label: 'Created' },
                { value: 'updated', label: 'Updated' },
                { value: 'deleted', label: 'Deleted' },
              ]}
              style={{ width: 200 }}
              clearable
            />

            <DatePickerInput
              type="range"
              placeholder="Pick date range"
              value={dateRange}
              onChange={setDateRange}
              style={{ width: 300 }}
              clearable
            />
          </Group>
        </Card>

        {/* Audit Logs Table */}
        <Card withBorder padding="0">
          {isLoading ? (
            <Stack align="center" py="xl">
              <Loader />
            </Stack>
          ) : auditLogs.length === 0 ? (
            <Stack align="center" py="xl">
              <Text c="dimmed">No audit logs found</Text>
            </Stack>
          ) : (
            <Table>
              <Table.Thead>
                <Table.Tr>
                  <Table.Th style={{ width: 40 }}></Table.Th>
                  <Table.Th>Timestamp</Table.Th>
                  <Table.Th>User</Table.Th>
                  <Table.Th>Entity</Table.Th>
                  <Table.Th>Action</Table.Th>
                  <Table.Th>IP Address</Table.Th>
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {auditLogs.map((log: any) => (
                  <>
                    <Table.Tr key={log.id} style={{ cursor: 'pointer' }}>
                      <Table.Td onClick={() => toggleRow(log.id)}>
                        <ActionIcon variant="subtle" size="sm">
                          {expandedRows.has(log.id) ? (
                            <IconChevronDown size={16} />
                          ) : (
                            <IconChevronRight size={16} />
                          )}
                        </ActionIcon>
                      </Table.Td>
                      <Table.Td>
                        <Text size="sm">{new Date(log.created_at).toLocaleString()}</Text>
                      </Table.Td>
                      <Table.Td>
                        <Text size="sm">{log.user?.username || 'System'}</Text>
                      </Table.Td>
                      <Table.Td>
                        <Text size="sm">
                          {log.entity_name} #{log.entity_id}
                        </Text>
                      </Table.Td>
                      <Table.Td>
                        <Badge color={getActionColor(log.action)}>{log.action}</Badge>
                      </Table.Td>
                      <Table.Td>
                        <Text size="sm" c="dimmed">{log.ip_address || 'N/A'}</Text>
                      </Table.Td>
                    </Table.Tr>
                    {expandedRows.has(log.id) && (
                      <Table.Tr>
                        <Table.Td colSpan={6} style={{ backgroundColor: 'var(--mantine-color-gray-0)' }}>
                          <Collapse in={expandedRows.has(log.id)}>
                            <Stack gap="md" p="md">
                              {log.user_agent && (
                                <div>
                                  <Text size="sm" fw={600}>User Agent:</Text>
                                  <Text size="xs" c="dimmed">{log.user_agent}</Text>
                                </div>
                              )}
                              {(log.before_state || log.after_state) && (
                                <div>
                                  <Text size="sm" fw={600} mb="xs">Changes:</Text>
                                  {renderDiff(log.before_state, log.after_state)}
                                </div>
                              )}
                            </Stack>
                          </Collapse>
                        </Table.Td>
                      </Table.Tr>
                    )}
                  </>
                ))}
              </Table.Tbody>
            </Table>
          )}
        </Card>

        {auditLogsData?.meta && (
          <Group justify="center">
            <Text size="sm" c="dimmed">
              Showing {auditLogsData.data.length} of {auditLogsData.meta.total} logs
            </Text>
          </Group>
        )}
      </Stack>
    </Container>
  );
}
