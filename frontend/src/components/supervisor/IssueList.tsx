import { useState } from 'react';
import {
  Stack,
  Card,
  Text,
  Badge,
  Group,
  Button,
  Loader,
  Select,
  Table,
  ActionIcon,
} from '@mantine/core';
import { IconEye, IconAlertTriangle } from '@tabler/icons-react';
import { useIssues } from '../../hooks/useIssues';
import { IssueDetailModal } from './IssueDetailModal';
import type { Issue } from '../../api/issues';

const getStatusColor = (status: string) => {
  switch (status) {
    case 'OPEN':
      return 'red';
    case 'ACKNOWLEDGED':
      return 'orange';
    case 'RESOLVED':
      return 'blue';
    case 'CLOSED':
      return 'green';
    default:
      return 'gray';
  }
};

const getSeverityColor = (severity: string) => {
  switch (severity) {
    case 'LOW':
      return 'gray';
    case 'MEDIUM':
      return 'yellow';
    case 'HIGH':
      return 'orange';
    case 'CRITICAL':
      return 'red';
    default:
      return 'gray';
  }
};

interface IssueListProps {
  lineId?: number;
}

export function IssueList({ lineId }: IssueListProps) {
  const [statusFilter, setStatusFilter] = useState<string | null>('OPEN');
  const [selectedIssue, setSelectedIssue] = useState<Issue | null>(null);
  const [modalOpened, setModalOpened] = useState(false);

  const { data: issuesData, isLoading } = useIssues({
    line_id: lineId,
    status: statusFilter || undefined,
  });

  const issues = issuesData?.data || [];

  const handleViewIssue = (issue: Issue) => {
    setSelectedIssue(issue);
    setModalOpened(true);
  };

  if (isLoading) {
    return (
      <Stack align="center" py="md">
        <Loader size="sm" />
      </Stack>
    );
  }

  return (
    <Stack>
      <Group justify="space-between">
        <Text size="lg" fw={600}>
          Issues & Problems
        </Text>
        <Select
          placeholder="Filter by status"
          value={statusFilter}
          onChange={setStatusFilter}
          data={[
            { value: '', label: 'All Statuses' },
            { value: 'OPEN', label: 'Open' },
            { value: 'ACKNOWLEDGED', label: 'Acknowledged' },
            { value: 'RESOLVED', label: 'Resolved' },
            { value: 'CLOSED', label: 'Closed' },
          ]}
          style={{ width: 200 }}
          clearable
        />
      </Group>

      {issues.length === 0 ? (
        <Card padding="xl" withBorder>
          <Stack align="center" gap="md">
            <Text c="dimmed" ta="center">
              No issues found
            </Text>
          </Stack>
        </Card>
      ) : (
        <Card padding="0" withBorder>
          <Table highlightOnHover>
            <Table.Thead>
              <Table.Tr>
                <Table.Th>Work Order</Table.Th>
                <Table.Th>Issue</Table.Th>
                <Table.Th>Type</Table.Th>
                <Table.Th>Status</Table.Th>
                <Table.Th>Reported</Table.Th>
                <Table.Th>Actions</Table.Th>
              </Table.Tr>
            </Table.Thead>
            <Table.Tbody>
              {issues.map((issue) => (
                <Table.Tr key={issue.id}>
                  <Table.Td>
                    <Text size="sm" fw={500}>
                      {issue.work_order?.order_no || 'N/A'}
                    </Text>
                  </Table.Td>
                  <Table.Td>
                    <Stack gap={4}>
                      <Text size="sm" fw={500}>
                        {issue.title}
                      </Text>
                      {issue.description && (
                        <Text size="xs" c="dimmed" lineClamp={1}>
                          {issue.description}
                        </Text>
                      )}
                    </Stack>
                  </Table.Td>
                  <Table.Td>
                    <Group gap="xs">
                      <Badge size="sm" color={getSeverityColor(issue.issue_type?.severity || 'MEDIUM')}>
                        {issue.issue_type?.name || 'Unknown'}
                      </Badge>
                      {issue.issue_type?.is_blocking && (
                        <IconAlertTriangle size={16} color="red" />
                      )}
                    </Group>
                  </Table.Td>
                  <Table.Td>
                    <Badge color={getStatusColor(issue.status)}>{issue.status}</Badge>
                  </Table.Td>
                  <Table.Td>
                    <Stack gap={2}>
                      <Text size="xs">{new Date(issue.reported_at).toLocaleString()}</Text>
                      <Text size="xs" c="dimmed">
                        by {issue.reported_by?.username || 'Unknown'}
                      </Text>
                    </Stack>
                  </Table.Td>
                  <Table.Td>
                    <ActionIcon variant="light" onClick={() => handleViewIssue(issue)}>
                      <IconEye size={18} />
                    </ActionIcon>
                  </Table.Td>
                </Table.Tr>
              ))}
            </Table.Tbody>
          </Table>
        </Card>
      )}

      {selectedIssue && (
        <IssueDetailModal
          issue={selectedIssue}
          opened={modalOpened}
          onClose={() => {
            setModalOpened(false);
            setSelectedIssue(null);
          }}
        />
      )}
    </Stack>
  );
}
