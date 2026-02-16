import { useMutation, useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { authApi } from '../api/auth';
import { useAuthStore } from '../stores/authStore';
import type { LoginCredentials } from '../types';

export const useAuth = () => {
  const navigate = useNavigate();
  const { user, isAuthenticated, setAuth, clearAuth, setUser } = useAuthStore();

  const loginMutation = useMutation({
    mutationFn: (credentials: LoginCredentials) => authApi.login(credentials),
    onSuccess: (data) => {
      setAuth(data.data.user, data.data.token);

      if (data.data.force_password_change) {
        navigate('/change-password');
      } else if (data.data.user.roles.some((r) => r.name === 'Operator')) {
        navigate('/operator/select-line');
      } else {
        navigate('/');
      }
    },
  });

  const logoutMutation = useMutation({
    mutationFn: () => authApi.logout(),
    onSuccess: () => {
      clearAuth();
      navigate('/login');
    },
    onError: () => {
      // Even if API call fails, clear local auth
      clearAuth();
      navigate('/login');
    },
  });

  const changePasswordMutation = useMutation({
    mutationFn: ({ currentPassword, newPassword }: { currentPassword: string; newPassword: string }) =>
      authApi.changePassword(currentPassword, newPassword),
    onSuccess: () => {
      // Update user to remove force_password_change flag
      if (user) {
        setUser({ ...user, force_password_change: false });
      }
      navigate('/operator/select-line');
    },
  });

  const { refetch: refetchMe } = useQuery({
    queryKey: ['auth', 'me'],
    queryFn: () => authApi.me(),
    enabled: false,
  });

  return {
    user,
    isAuthenticated,
    login: loginMutation.mutate,
    logout: logoutMutation.mutate,
    changePassword: changePasswordMutation.mutate,
    isLoggingIn: loginMutation.isPending,
    isLoggingOut: logoutMutation.isPending,
    isChangingPassword: changePasswordMutation.isPending,
    loginError: loginMutation.error,
    changePasswordError: changePasswordMutation.error,
    refetchMe,
  };
};
