import { Container, Group, Button, Title } from '@mantine/core';
import { WorkOrderQueue } from '../../components/operator/WorkOrderQueue';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';

export const QueuePage = () => {
  const navigate = useNavigate();
  const { logout, user } = useAuth();

  return (
    <div>
      <Group
        justify="space-between"
        p="md"
        style={{ borderBottom: '1px solid #e9ecef', backgroundColor: 'white' }}
      >
        <Title order={3}>OpenMES - {user?.username}</Title>
        <Group gap="xs">
          <Button variant="subtle" onClick={() => navigate('/operator/select-line')}>
            Change Line
          </Button>
          <Button variant="subtle" color="red" onClick={() => logout()}>
            Logout
          </Button>
        </Group>
      </Group>
      <Container size="md" py="md">
        <WorkOrderQueue />
      </Container>
    </div>
  );
};
