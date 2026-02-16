import { Card, Text, Stack, Button, Title } from '@mantine/core';
import { useNavigate } from 'react-router-dom';
import { useAuthStore } from '../../stores/authStore';

export const LineSelector = () => {
  const navigate = useNavigate();
  const { user, setSelectedLine } = useAuthStore();

  const handleSelectLine = (lineId: number) => {
    setSelectedLine(lineId);
    navigate('/operator/queue');
  };

  if (!user || !user.lines || user.lines.length === 0) {
    return (
      <Stack align="center" gap="md" style={{ padding: '2rem' }}>
        <Title order={2}>No Lines Assigned</Title>
        <Text c="dimmed">You don't have access to any production lines. Please contact your administrator.</Text>
      </Stack>
    );
  }

  return (
    <Stack gap="md" style={{ padding: '2rem', maxWidth: 600, margin: '0 auto' }}>
      <Title order={2}>Select Production Line</Title>
      <Text c="dimmed">Choose a line to view work orders</Text>

      <Stack gap="md">
        {user.lines
          .filter((line) => line.is_active)
          .map((line) => (
            <Card key={line.id} shadow="sm" padding="lg" radius="md" withBorder style={{ cursor: 'pointer' }}>
              <Stack gap="xs">
                <Text fw={500} size="lg">
                  {line.name}
                </Text>
                <Text size="sm" c="dimmed">
                  Code: {line.code}
                </Text>
                {line.description && (
                  <Text size="sm" c="dimmed">
                    {line.description}
                  </Text>
                )}
                <Button onClick={() => handleSelectLine(line.id)} fullWidth mt="md" size="md">
                  Select Line
                </Button>
              </Stack>
            </Card>
          ))}
      </Stack>
    </Stack>
  );
};
