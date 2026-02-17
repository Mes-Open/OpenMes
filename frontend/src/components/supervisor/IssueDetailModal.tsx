import { Modal, Stack, Group, Text, Badge, Button, Textarea, Divider } from '@mantine/core';
import { useForm } from '@mantine/form';
import { IconCheck, IconX, IconAlertTriangle } from '@tabler/icons-react';
import { useAcknowledgeIssue, useResolveIssue, useCloseIssue } from '../../hooks/useIssues';
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

interface IssueDetailModalProps {
  issue: Issue;
  opened: boolean;
  onClose: () => void;
}

export function IssueDetailModal({ issue, opened, onClose }: IssueDetailModalProps) {
  const acknowledgeIssue = useAcknowledgeIssue();
  const resolveIssue = useResolveIssue();
  const closeIssue = useCloseIssue();

  const form = useForm({
    initialValues: {
      resolution_notes: issue.resolution_notes || '',
    },
    validate: {
      resolution_notes: (value) =>
        !value && issue.status !== 'RESOLVED' ? 'Resolution notes are required' : null,
    },
  });

  const handleAcknowledge = () => {
    acknowledgeIssue.mutate(issue.id, {
      onSuccess: () => {
        onClose();
      },
    });
  };

  const handleResolve = () => {
    if (!form.values.resolution_notes) {
      form.validateField('resolution_notes');
      return;
    }

    resolveIssue.mutate(
      {
        issueId: issue.id,
        data: { resolution_notes: form.values.resolution_notes },
      },
      {
        onSuccess: () => {
          onClose();
        },
      }
    );
  };

  const handleClose = () => {
    closeIssue.mutate(issue.id, {
      onSuccess: () => {
        onClose();
      },
    });
  };

  const canAcknowledge = issue.status === 'OPEN';
  const canResolve = issue.status === 'OPEN' || issue.status === 'ACKNOWLEDGED';
  const canClose = issue.status === 'RESOLVED';

  return (
    <Modal opened={opened} onClose={onClose} title="Issue Details" size="lg">
      <Stack gap="md">
        {/* Status and Type */}
        <Group justify="space-between">
          <Badge color={getStatusColor(issue.status)} size="lg">
            {issue.status}
          </Badge>
          <Group gap="xs">
            <Badge size="lg" color={getSeverityColor(issue.issue_type?.severity || 'MEDIUM')}>
              {issue.issue_type?.severity}
            </Badge>
            {issue.issue_type?.is_blocking && (
              <Badge color="red" size="lg" leftSection={<IconAlertTriangle size={16} />}>
                BLOCKING
              </Badge>
            )}
          </Group>
        </Group>

        <Divider />

        {/* Issue Information */}
        <div>
          <Text size="sm" c="dimmed" mb={4}>
            Work Order
          </Text>
          <Text fw={500}>{issue.work_order?.order_no || 'N/A'}</Text>
        </div>

        <div>
          <Text size="sm" c="dimmed" mb={4}>
            Issue Type
          </Text>
          <Text fw={500}>{issue.issue_type?.name || 'Unknown'}</Text>
        </div>

        <div>
          <Text size="sm" c="dimmed" mb={4}>
            Title
          </Text>
          <Text fw={500}>{issue.title}</Text>
        </div>

        {issue.description && (
          <div>
            <Text size="sm" c="dimmed" mb={4}>
              Description
            </Text>
            <Text>{issue.description}</Text>
          </div>
        )}

        <Divider />

        {/* Timeline */}
        <div>
          <Text size="sm" c="dimmed" mb={4}>
            Reported
          </Text>
          <Text size="sm">
            {new Date(issue.reported_at).toLocaleString()} by {issue.reported_by?.username || 'Unknown'}
          </Text>
        </div>

        {issue.acknowledged_at && (
          <div>
            <Text size="sm" c="dimmed" mb={4}>
              Acknowledged
            </Text>
            <Text size="sm">
              {new Date(issue.acknowledged_at).toLocaleString()}
              {issue.assigned_to && ` by ${issue.assigned_to.username}`}
            </Text>
          </div>
        )}

        {issue.resolved_at && (
          <div>
            <Text size="sm" c="dimmed" mb={4}>
              Resolved
            </Text>
            <Text size="sm">{new Date(issue.resolved_at).toLocaleString()}</Text>
          </div>
        )}

        {issue.closed_at && (
          <div>
            <Text size="sm" c="dimmed" mb={4}>
              Closed
            </Text>
            <Text size="sm">{new Date(issue.closed_at).toLocaleString()}</Text>
          </div>
        )}

        {/* Resolution Notes */}
        {(canResolve || issue.resolution_notes) && (
          <>
            <Divider />
            <Textarea
              label="Resolution Notes"
              placeholder="Describe how the issue was resolved..."
              minRows={3}
              maxRows={6}
              {...form.getInputProps('resolution_notes')}
              disabled={issue.status === 'RESOLVED' || issue.status === 'CLOSED'}
            />
          </>
        )}

        {/* Action Buttons */}
        <Group justify="flex-end" mt="md">
          <Button variant="default" onClick={onClose}>
            Close
          </Button>

          {canAcknowledge && (
            <Button
              color="orange"
              onClick={handleAcknowledge}
              loading={acknowledgeIssue.isPending}
              leftSection={<IconAlertTriangle size={18} />}
            >
              Acknowledge & Assign to Me
            </Button>
          )}

          {canResolve && (
            <Button
              color="blue"
              onClick={handleResolve}
              loading={resolveIssue.isPending}
              leftSection={<IconCheck size={18} />}
            >
              Mark as Resolved
            </Button>
          )}

          {canClose && (
            <Button
              color="green"
              onClick={handleClose}
              loading={closeIssue.isPending}
              leftSection={<IconX size={18} />}
            >
              Close Issue
            </Button>
          )}
        </Group>
      </Stack>
    </Modal>
  );
}
