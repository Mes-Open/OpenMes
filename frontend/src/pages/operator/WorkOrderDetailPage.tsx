import { Container, Group, Title } from '@mantine/core';
import { WorkOrderDetail } from '../../components/operator/WorkOrderDetail';
import { useAuth } from '../../hooks/useAuth';

export const WorkOrderDetailPage = () => {
  const { user } = useAuth();

  return (
    <div>
      <Group
        justify="space-between"
        p="md"
        style={{ borderBottom: '1px solid #e9ecef', backgroundColor: 'white' }}
      >
        <Title order={3}>OpenMES - {user?.username}</Title>
      </Group>
      <Container size="lg" py="md">
        <WorkOrderDetail />
      </Container>
    </div>
  );
};
