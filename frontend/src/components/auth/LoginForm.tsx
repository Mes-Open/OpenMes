import { useState } from 'react';
import { TextInput, PasswordInput, Button, Paper, Title, Text, Stack } from '@mantine/core';
import { useAuth } from '../../hooks/useAuth';

export const LoginForm = () => {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const { login, isLoggingIn, loginError } = useAuth();

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    login({ username, password });
  };

  return (
    <Paper withBorder shadow="md" p={30} radius="md" style={{ maxWidth: 420, width: '100%' }}>
      <Title order={2} ta="center" mb="md">
        🏭 OpenMES
      </Title>
      <Text c="dimmed" size="sm" ta="center" mb="xl">
        Manufacturing Execution System
      </Text>

      <form onSubmit={handleSubmit}>
        <Stack gap="md">
          <TextInput
            label="Username"
            placeholder="Enter your username"
            value={username}
            onChange={(e) => setUsername(e.target.value)}
            required
            size="md"
          />

          <PasswordInput
            label="Password"
            placeholder="Enter your password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            size="md"
          />

          {loginError && (
            <Text c="red" size="sm">
              {(loginError as any)?.response?.data?.message || 'Login failed. Please check your credentials.'}
            </Text>
          )}

          <Button type="submit" fullWidth size="md" loading={isLoggingIn}>
            Sign In
          </Button>
        </Stack>
      </form>
    </Paper>
  );
};
