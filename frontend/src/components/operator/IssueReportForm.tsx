import { Modal, Select, TextInput, Textarea, Button, Stack, Group, Badge } from '@mantine/core';
import { useForm } from '@mantine/form';
import { useIssueTypes, useReportProblem } from '../../hooks/useIssues';
import { IconAlertTriangle } from '@tabler/icons-react';

interface IssueReportFormProps {
  opened: boolean;
  onClose: () => void;
  batchStepId: number;
  stepName: string;
}

export function IssueReportForm({ opened, onClose, batchStepId, stepName }: IssueReportFormProps) {
  const { data: issueTypes, isLoading: loadingTypes } = useIssueTypes();
  const reportProblem = useReportProblem();

  const form = useForm({
    initialValues: {
      issue_type_id: '',
      title: '',
      description: '',
    },
    validate: {
      issue_type_id: (value) => (!value ? 'Please select an issue type' : null),
      title: (value) => (!value ? 'Please enter a title' : null),
    },
  });

  const handleSubmit = form.onSubmit((values) => {
    reportProblem.mutate(
      {
        batchStepId,
        data: {
          issue_type_id: parseInt(values.issue_type_id),
          title: values.title,
          description: values.description || undefined,
        },
      },
      {
        onSuccess: () => {
          form.reset();
          onClose();
        },
      }
    );
  });

  const selectedIssueType = issueTypes?.find(
    (type) => type.id.toString() === form.values.issue_type_id
  );

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title={
        <Group>
          <IconAlertTriangle size={24} color="orange" />
          <span>Report Problem</span>
        </Group>
      }
      size="md"
    >
      <form onSubmit={handleSubmit}>
        <Stack>
          <div>
            <strong>Step:</strong> {stepName}
          </div>

          <Select
            label="Issue Type"
            placeholder="Select issue type"
            data={
              issueTypes?.map((type) => ({
                value: type.id.toString(),
                label: `${type.name} (${type.severity})`,
              })) || []
            }
            {...form.getInputProps('issue_type_id')}
            disabled={loadingTypes}
            required
          />

          {selectedIssueType?.is_blocking && (
            <Badge color="red" size="lg" variant="light">
              ⚠️ This issue will BLOCK the work order
            </Badge>
          )}

          <TextInput
            label="Issue Title"
            placeholder="Brief description of the problem"
            {...form.getInputProps('title')}
            required
          />

          <Textarea
            label="Description (optional)"
            placeholder="Provide additional details about the problem..."
            minRows={3}
            maxRows={6}
            {...form.getInputProps('description')}
          />

          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={onClose} disabled={reportProblem.isPending}>
              Cancel
            </Button>
            <Button type="submit" color="orange" loading={reportProblem.isPending}>
              Report Problem
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  );
}
