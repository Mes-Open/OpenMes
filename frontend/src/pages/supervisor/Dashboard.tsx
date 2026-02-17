import { useState } from 'react';
import {
  Container,
  Title,
  Stack,
  Group,
  Card,
  Text,
  Badge,
  Select,
  Grid,
  RingProgress,
  Center,
  Tabs,
  Table,
  Loader,
} from '@mantine/core';
import {
  IconAlertTriangle,
  IconChecks,
  IconClock,
  IconBox,
  IconClipboardList,
  IconTrendingUp,
  IconActivity,
} from '@tabler/icons-react';
import { LineChart, Line, BarChart, Bar, PieChart, Pie, Cell, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import { useAuthStore } from '../../stores/authStore';
import { useLineIssueStats } from '../../hooks/useIssues';
import { IssueList } from '../../components/supervisor/IssueList';
import {
  useOverviewStats,
  useThroughput,
  useCycleTime,
  useIssueStats,
  useStepPerformance,
} from '../../hooks/useAnalytics';

export function SupervisorDashboard() {
  const { user } = useAuthStore();
  const userLines = user?.lines || [];

  const [selectedLineId, setSelectedLineId] = useState<string | null>(
    userLines.length > 0 ? userLines[0].id.toString() : null
  );

  const lineId = selectedLineId ? parseInt(selectedLineId) : undefined;
  const { data: issueStats } = useLineIssueStats(lineId);
  const { data: overviewStats, isLoading: loadingOverview } = useOverviewStats(lineId);
  const { data: throughputData, isLoading: loadingThroughput } = useThroughput(lineId, 30);
  const { data: cycleTimeData, isLoading: loadingCycleTime } = useCycleTime(lineId, 30);
  const { data: issueStatsData, isLoading: loadingIssueStats } = useIssueStats(lineId, 30);
  const { data: stepPerformance, isLoading: loadingStepPerf } = useStepPerformance(lineId, 30);

  const selectedLine = userLines.find((line) => line.id.toString() === selectedLineId);

  const totalIssues = issueStats?.total || 0;
  const openIssues = (issueStats?.open || 0) + (issueStats?.acknowledged || 0);
  const blockingIssues = issueStats?.blocking || 0;

  // Chart colors
  const COLORS = ['#228be6', '#40c057', '#fab005', '#fa5252', '#be4bdb', '#15aabf'];

  return (
    <Container size="xl" py="xl">
      <Stack gap="xl">
        {/* Header */}
        <Group justify="space-between">
          <Title order={1}>Supervisor Dashboard</Title>
          {userLines.length > 1 && (
            <Select
              placeholder="Select production line"
              value={selectedLineId}
              onChange={setSelectedLineId}
              data={userLines.map((line) => ({
                value: line.id.toString(),
                label: line.name,
              }))}
              style={{ width: 250 }}
            />
          )}
        </Group>

        {selectedLine && (
          <>
            {/* Line Header */}
            <Card shadow="sm" padding="lg" radius="md" withBorder>
              <Stack gap="xs">
                <Text size="xl" fw={700}>
                  {selectedLine.name}
                </Text>
                {selectedLine.description && (
                  <Text size="sm" c="dimmed">
                    {selectedLine.description}
                  </Text>
                )}
              </Stack>
            </Card>

            {/* KPI Cards */}
            <Grid>
              <Grid.Col span={{ base: 12, sm: 6, md: 3 }}>
                <Card shadow="sm" padding="lg" radius="md" withBorder>
                  <Stack gap="xs" align="center">
                    <IconBox size={32} color="blue" />
                    <Text size="xl" fw={700}>
                      {totalIssues}
                    </Text>
                    <Text size="sm" c="dimmed" ta="center">
                      Total Issues
                    </Text>
                  </Stack>
                </Card>
              </Grid.Col>

              <Grid.Col span={{ base: 12, sm: 6, md: 3 }}>
                <Card shadow="sm" padding="lg" radius="md" withBorder>
                  <Stack gap="xs" align="center">
                    <IconAlertTriangle size={32} color={openIssues > 0 ? 'red' : 'gray'} />
                    <Text size="xl" fw={700} c={openIssues > 0 ? 'red' : undefined}>
                      {openIssues}
                    </Text>
                    <Text size="sm" c="dimmed" ta="center">
                      Open Issues
                    </Text>
                  </Stack>
                </Card>
              </Grid.Col>

              <Grid.Col span={{ base: 12, sm: 6, md: 3 }}>
                <Card shadow="sm" padding="lg" radius="md" withBorder>
                  <Stack gap="xs" align="center">
                    <IconClock size={32} color={blockingIssues > 0 ? 'orange' : 'gray'} />
                    <Text size="xl" fw={700} c={blockingIssues > 0 ? 'orange' : undefined}>
                      {blockingIssues}
                    </Text>
                    <Text size="sm" c="dimmed" ta="center">
                      Blocking Issues
                    </Text>
                  </Stack>
                </Card>
              </Grid.Col>

              <Grid.Col span={{ base: 12, sm: 6, md: 3 }}>
                <Card shadow="sm" padding="lg" radius="md" withBorder>
                  <Stack gap="xs" align="center">
                    <IconChecks size={32} color="green" />
                    <Text size="xl" fw={700}>
                      {issueStats?.resolved || 0}
                    </Text>
                    <Text size="sm" c="dimmed" ta="center">
                      Resolved Issues
                    </Text>
                  </Stack>
                </Card>
              </Grid.Col>
            </Grid>

            {/* Production Overview KPIs */}
            <Grid>
              <Grid.Col span={{ base: 12, sm: 6, md: 3 }}>
                <Card shadow="sm" padding="lg" radius="md" withBorder>
                  <Stack gap="xs" align="center">
                    <IconClipboardList size={32} color="blue" />
                    <Text size="xl" fw={700}>
                      {loadingOverview ? '...' : overviewStats?.active_work_orders || 0}
                    </Text>
                    <Text size="sm" c="dimmed" ta="center">
                      Active Work Orders
                    </Text>
                  </Stack>
                </Card>
              </Grid.Col>

              <Grid.Col span={{ base: 12, sm: 6, md: 3 }}>
                <Card shadow="sm" padding="lg" radius="md" withBorder>
                  <Stack gap="xs" align="center">
                    <IconChecks size={32} color="green" />
                    <Text size="xl" fw={700}>
                      {loadingOverview ? '...' : overviewStats?.completed_work_orders || 0}
                    </Text>
                    <Text size="sm" c="dimmed" ta="center">
                      Completed Work Orders
                    </Text>
                  </Stack>
                </Card>
              </Grid.Col>

              <Grid.Col span={{ base: 12, sm: 6, md: 3 }}>
                <Card shadow="sm" padding="lg" radius="md" withBorder>
                  <Stack gap="xs" align="center">
                    <IconActivity size={32} color="blue" />
                    <Text size="xl" fw={700}>
                      {loadingOverview ? '...' : overviewStats?.active_batches || 0}
                    </Text>
                    <Text size="sm" c="dimmed" ta="center">
                      Active Batches
                    </Text>
                  </Stack>
                </Card>
              </Grid.Col>

              <Grid.Col span={{ base: 12, sm: 6, md: 3 }}>
                <Card shadow="sm" padding="lg" radius="md" withBorder>
                  <Stack gap="xs" align="center">
                    <IconAlertTriangle size={32} color={overviewStats?.blocked_work_orders ? 'red' : 'gray'} />
                    <Text size="xl" fw={700} c={overviewStats?.blocked_work_orders ? 'red' : undefined}>
                      {loadingOverview ? '...' : overviewStats?.blocked_work_orders || 0}
                    </Text>
                    <Text size="sm" c="dimmed" ta="center">
                      Blocked Work Orders
                    </Text>
                  </Stack>
                </Card>
              </Grid.Col>
            </Grid>

            {/* Analytics Tabs */}
            <Tabs defaultValue="throughput">
              <Tabs.List>
                <Tabs.Tab value="throughput" leftSection={<IconTrendingUp size={16} />}>
                  Throughput
                </Tabs.Tab>
                <Tabs.Tab value="cycle-time" leftSection={<IconClock size={16} />}>
                  Cycle Time
                </Tabs.Tab>
                <Tabs.Tab value="issues" leftSection={<IconAlertTriangle size={16} />}>
                  Issues
                </Tabs.Tab>
                <Tabs.Tab value="steps" leftSection={<IconActivity size={16} />}>
                  Step Performance
                </Tabs.Tab>
              </Tabs.List>

              <Tabs.Panel value="throughput" pt="lg">
                <Card shadow="sm" padding="lg" radius="md" withBorder>
                  <Stack gap="md">
                    <div>
                      <Text size="lg" fw={600}>
                        Daily Production Throughput
                      </Text>
                      <Text size="sm" c="dimmed">
                        Last 30 days - Average: {throughputData?.average_daily_throughput.toFixed(2) || 0} units/day
                      </Text>
                    </div>
                    {loadingThroughput ? (
                      <Center py="xl">
                        <Loader />
                      </Center>
                    ) : (
                      <ResponsiveContainer width="100%" height={300}>
                        <LineChart data={throughputData?.daily_production || []}>
                          <CartesianGrid strokeDasharray="3 3" />
                          <XAxis dataKey="date" />
                          <YAxis />
                          <Tooltip />
                          <Legend />
                          <Line type="monotone" dataKey="total_produced" stroke="#228be6" strokeWidth={2} name="Units Produced" />
                        </LineChart>
                      </ResponsiveContainer>
                    )}
                  </Stack>
                </Card>
              </Tabs.Panel>

              <Tabs.Panel value="cycle-time" pt="lg">
                <Card shadow="sm" padding="lg" radius="md" withBorder>
                  <Stack gap="md">
                    <div>
                      <Text size="lg" fw={600}>
                        Batch Cycle Time
                      </Text>
                      <Text size="sm" c="dimmed">
                        Average: {cycleTimeData?.average_cycle_time_hours.toFixed(2) || 0} hours ({cycleTimeData?.total_batches || 0} batches)
                      </Text>
                    </div>
                    {loadingCycleTime ? (
                      <Center py="xl">
                        <Loader />
                      </Center>
                    ) : cycleTimeData && cycleTimeData.batches.length > 0 ? (
                      <Table>
                        <Table.Thead>
                          <Table.Tr>
                            <Table.Th>Work Order</Table.Th>
                            <Table.Th>Product</Table.Th>
                            <Table.Th>Quantity</Table.Th>
                            <Table.Th>Cycle Time</Table.Th>
                          </Table.Tr>
                        </Table.Thead>
                        <Table.Tbody>
                          {cycleTimeData.batches.slice(0, 10).map((batch) => (
                            <Table.Tr key={batch.batch_id}>
                              <Table.Td>{batch.work_order_no}</Table.Td>
                              <Table.Td>{batch.product_type}</Table.Td>
                              <Table.Td>{batch.produced_qty} / {batch.target_qty}</Table.Td>
                              <Table.Td>{batch.cycle_time_hours.toFixed(2)} hrs</Table.Td>
                            </Table.Tr>
                          ))}
                        </Table.Tbody>
                      </Table>
                    ) : (
                      <Text c="dimmed" ta="center" py="xl">
                        No completed batches in the selected period
                      </Text>
                    )}
                  </Stack>
                </Card>
              </Tabs.Panel>

              <Tabs.Panel value="issues" pt="lg">
                <Grid>
                  <Grid.Col span={{ base: 12, md: 6 }}>
                    <Card shadow="sm" padding="lg" radius="md" withBorder>
                      <Stack gap="md">
                        <Text size="lg" fw={600}>
                          Issues by Type
                        </Text>
                        {loadingIssueStats ? (
                          <Center py="xl">
                            <Loader />
                          </Center>
                        ) : issueStatsData && issueStatsData.by_type.length > 0 ? (
                          <ResponsiveContainer width="100%" height={250}>
                            <PieChart>
                              <Pie
                                data={issueStatsData.by_type}
                                dataKey="count"
                                nameKey="type_name"
                                cx="50%"
                                cy="50%"
                                outerRadius={80}
                                label
                              >
                                {issueStatsData.by_type.map((entry, index) => (
                                  <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                                ))}
                              </Pie>
                              <Tooltip />
                              <Legend />
                            </PieChart>
                          </ResponsiveContainer>
                        ) : (
                          <Text c="dimmed" ta="center">No issues in period</Text>
                        )}
                      </Stack>
                    </Card>
                  </Grid.Col>

                  <Grid.Col span={{ base: 12, md: 6 }}>
                    <Card shadow="sm" padding="lg" radius="md" withBorder>
                      <Stack gap="md">
                        <Text size="lg" fw={600}>
                          Issue Resolution
                        </Text>
                        <div>
                          <Text size="sm" c="dimmed">
                            Average Resolution Time
                          </Text>
                          <Text size="xl" fw={700}>
                            {issueStatsData?.average_resolution_time_hours.toFixed(2) || 0} hours
                          </Text>
                        </div>
                        {loadingIssueStats ? (
                          <Center py="xl">
                            <Loader />
                          </Center>
                        ) : issueStatsData && issueStatsData.by_status.length > 0 ? (
                          <ResponsiveContainer width="100%" height={200}>
                            <BarChart data={issueStatsData.by_status}>
                              <CartesianGrid strokeDasharray="3 3" />
                              <XAxis dataKey="status" />
                              <YAxis />
                              <Tooltip />
                              <Bar dataKey="count" fill="#228be6" />
                            </BarChart>
                          </ResponsiveContainer>
                        ) : (
                          <Text c="dimmed" ta="center">No issue data</Text>
                        )}
                      </Stack>
                    </Card>
                  </Grid.Col>
                </Grid>
              </Tabs.Panel>

              <Tabs.Panel value="steps" pt="lg">
                <Card shadow="sm" padding="lg" radius="md" withBorder>
                  <Stack gap="md">
                    <Text size="lg" fw={600}>
                      Step Performance (Last 30 days)
                    </Text>
                    {loadingStepPerf ? (
                      <Center py="xl">
                        <Loader />
                      </Center>
                    ) : stepPerformance && stepPerformance.length > 0 ? (
                      <ResponsiveContainer width="100%" height={300}>
                        <BarChart data={stepPerformance.slice(0, 10)}>
                          <CartesianGrid strokeDasharray="3 3" />
                          <XAxis dataKey="step_name" angle={-45} textAnchor="end" height={100} />
                          <YAxis label={{ value: 'Avg Duration (min)', angle: -90, position: 'insideLeft' }} />
                          <Tooltip />
                          <Bar dataKey="average_duration_minutes" fill="#40c057" name="Avg Duration (min)" />
                        </BarChart>
                      </ResponsiveContainer>
                    ) : (
                      <Text c="dimmed" ta="center" py="xl">
                        No step performance data available
                      </Text>
                    )}
                  </Stack>
                </Card>
              </Tabs.Panel>
            </Tabs>

            {/* Resolution Rate */}
            {totalIssues > 0 && (
              <Card shadow="sm" padding="lg" radius="md" withBorder>
                <Group justify="space-between" wrap="nowrap">
                  <div style={{ flex: 1 }}>
                    <Text size="lg" fw={600} mb="xs">
                      Issue Resolution Rate
                    </Text>
                    <Text size="sm" c="dimmed">
                      {issueStats?.closed || 0} of {totalIssues} issues closed
                    </Text>
                  </div>
                  <Center>
                    <RingProgress
                      size={120}
                      thickness={12}
                      sections={[
                        {
                          value: ((issueStats?.closed || 0) / totalIssues) * 100,
                          color: 'green',
                        },
                      ]}
                      label={
                        <Text size="lg" fw={700} ta="center">
                          {Math.round(((issueStats?.closed || 0) / totalIssues) * 100)}%
                        </Text>
                      }
                    />
                  </Center>
                </Group>
              </Card>
            )}

            {/* Issue List */}
            <IssueList lineId={lineId} />
          </>
        )}

        {userLines.length === 0 && (
          <Card shadow="sm" padding="xl" radius="md" withBorder>
            <Stack align="center" gap="md">
              <IconAlertTriangle size={48} color="orange" />
              <Text size="lg" fw={500}>
                No Production Lines Assigned
              </Text>
              <Text c="dimmed" ta="center">
                You don't have access to any production lines. Please contact an administrator.
              </Text>
            </Stack>
          </Card>
        )}
      </Stack>
    </Container>
  );
}
