import { Center, Stack } from '@mantine/core';
import { LoginForm } from '../../components/auth/LoginForm';
import { useAuthStore } from '../../stores/authStore';
import { Navigate } from 'react-router-dom';

export const LoginPage = () => {
  const { isAuthenticated } = useAuthStore();

  if (isAuthenticated) {
    return <Navigate to="/operator/select-line" replace />;
  }

  return (
    <Center style={{ minHeight: '100vh', backgroundColor: '#f5f5f5' }}>
      <Stack>
        <LoginForm />
      </Stack>
    </Center>
  );
};
