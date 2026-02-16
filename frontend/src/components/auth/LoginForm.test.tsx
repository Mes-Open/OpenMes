import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { LoginForm } from './LoginForm';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MantineProvider } from '@mantine/core';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { retry: false },
    mutations: { retry: false },
  },
});

const renderWithProviders = (component: React.ReactElement) => {
  return render(
    <QueryClientProvider client={queryClient}>
      <MantineProvider>
        <BrowserRouter>{component}</BrowserRouter>
      </MantineProvider>
    </QueryClientProvider>
  );
};

describe('LoginForm', () => {
  it('renders login form with username and password fields', () => {
    renderWithProviders(<LoginForm />);

    expect(screen.getByText('🏭 OpenMES')).toBeInTheDocument();
    expect(screen.getByText('Manufacturing Execution System')).toBeInTheDocument();
    expect(screen.getByLabelText(/username/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/password/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /sign in/i })).toBeInTheDocument();
  });

  it('displays username and password input fields as required', () => {
    renderWithProviders(<LoginForm />);

    const usernameInput = screen.getByLabelText(/username/i) as HTMLInputElement;
    const passwordInput = screen.getByLabelText(/password/i) as HTMLInputElement;

    expect(usernameInput).toBeRequired();
    expect(passwordInput).toBeRequired();
  });
});
