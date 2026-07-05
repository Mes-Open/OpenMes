import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { getTrash, restoreTrash } from '@/api/trash';

export function useTrash(type?: string) {
  return useQuery({ queryKey: ['trash', type ?? ''], queryFn: () => getTrash(type) });
}

export function useRestoreTrash() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ type, id }: { type: string; id: number }) => restoreTrash(type, id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['trash'] }),
  });
}
