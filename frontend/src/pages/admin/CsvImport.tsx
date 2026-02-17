import { useState } from 'react';
import {
  Container,
  Title,
  Stack,
  Stepper,
  Button,
  Group,
  FileButton,
  Text,
  Card,
  Table,
  Select,
  TextInput,
  Badge,
  Alert,
  Progress,
  Checkbox,
} from '@mantine/core';
import { IconUpload, IconCheck, IconX, IconAlertCircle } from '@tabler/icons-react';
import {
  useUploadCsv,
  useExecuteImport,
  useImportStatus,
  useMappingTemplates,
} from '../../hooks/useCsvImport';
import type { CsvUploadResponse, ColumnMapping, ImportMapping } from '../../api/csvImport';

export function CsvImportPage() {
  const [active, setActive] = useState(0);
  const [file, setFile] = useState<File | null>(null);
  const [uploadData, setUploadData] = useState<CsvUploadResponse | null>(null);
  const [mapping, setMapping] = useState<ColumnMapping>({});
  const [importStrategy, setImportStrategy] = useState<'update_or_create' | 'skip_existing' | 'error_on_duplicate'>('update_or_create');
  const [saveTemplate, setSaveTemplate] = useState(false);
  const [templateName, setTemplateName] = useState('');
  const [importId, setImportId] = useState<number | null>(null);

  const uploadCsv = useUploadCsv();
  const executeImport = useExecuteImport();
  const { data: importStatus } = useImportStatus(importId);
  const { data: templates } = useMappingTemplates();

  const handleFileSelect = async (selectedFile: File | null) => {
    if (!selectedFile) return;

    setFile(selectedFile);
    uploadCsv.mutate(selectedFile, {
      onSuccess: (data) => {
        setUploadData(data);

        // Initialize default mapping
        const defaultMapping: ColumnMapping = {};
        const fieldMappings = [
          { field: 'order_no', label: 'Order Number' },
          { field: 'line_code', label: 'Line Code' },
          { field: 'product_type_code', label: 'Product Type Code' },
          { field: 'planned_qty', label: 'Planned Quantity' },
          { field: 'priority', label: 'Priority' },
          { field: 'due_date', label: 'Due Date' },
          { field: 'description', label: 'Description' },
        ];

        fieldMappings.forEach(({ field }) => {
          const matchingHeader = data.headers.find(h =>
            h.toLowerCase().includes(field.toLowerCase().replace('_', ' '))
          );
          if (matchingHeader) {
            defaultMapping[field] = { csv_column: matchingHeader };
          }
        });

        setMapping(defaultMapping);
        setActive(1);
      },
    });
  };

  const handleMappingChange = (field: string, csvColumn: string) => {
    setMapping(prev => ({
      ...prev,
      [field]: { csv_column: csvColumn },
    }));
  };

  const handleExecuteImport = () => {
    if (!uploadData) return;

    const importMapping: ImportMapping = {
      import_strategy: importStrategy,
      columns: mapping,
    };

    executeImport.mutate(
      {
        upload_id: uploadData.upload_id,
        mapping: importMapping,
        save_mapping_template: saveTemplate,
        mapping_template_name: saveTemplate ? templateName : undefined,
      },
      {
        onSuccess: (data) => {
          setImportId(data.import_id);
          setActive(2);
        },
      }
    );
  };

  const requiredFields = ['order_no', 'line_code', 'product_type_code', 'planned_qty'];
  const allRequiredMapped = requiredFields.every(field => mapping[field]?.csv_column);

  return (
    <Container size="xl" py="xl">
      <Stack gap="xl">
        <Title order={1}>Import Work Orders from CSV</Title>

        <Stepper active={active} onStepClick={setActive} allowNextStepsSelect={false}>
          {/* Step 1: Upload */}
          <Stepper.Step label="Upload" description="Select CSV file">
            <Stack gap="md" mt="xl">
              <FileButton onChange={handleFileSelect} accept=".csv,text/csv">
                {(props) => (
                  <Button {...props} leftSection={<IconUpload size={18} />} loading={uploadCsv.isPending}>
                    Select CSV File
                  </Button>
                )}
              </FileButton>

              {file && (
                <Card withBorder>
                  <Group justify="space-between">
                    <div>
                      <Text fw={500}>{file.name}</Text>
                      <Text size="sm" c="dimmed">
                        {(file.size / 1024).toFixed(2)} KB
                      </Text>
                    </div>
                    {uploadData && <Badge color="green">Uploaded</Badge>}
                  </Group>
                </Card>
              )}

              {uploadData && (
                <Alert icon={<IconCheck size={18} />} title="File uploaded successfully" color="green">
                  <Text size="sm">
                    Found {uploadData.total_rows} rows with {uploadData.headers.length} columns
                  </Text>
                </Alert>
              )}
            </Stack>
          </Stepper.Step>

          {/* Step 2: Map Columns */}
          <Stepper.Step label="Map Columns" description="Match CSV columns to fields">
            <Stack gap="md" mt="xl">
              <Select
                label="Import Strategy"
                description="How to handle existing work orders with the same order number"
                value={importStrategy}
                onChange={(value) => setImportStrategy(value as any)}
                data={[
                  { value: 'update_or_create', label: 'Update existing or create new' },
                  { value: 'skip_existing', label: 'Skip existing work orders' },
                  { value: 'error_on_duplicate', label: 'Error on duplicate order numbers' },
                ]}
              />

              <Card withBorder>
                <Stack gap="xs">
                  <Text fw={600}>Column Mapping</Text>
                  <Text size="sm" c="dimmed">
                    Map CSV columns to work order fields. Required fields are marked with *
                  </Text>
                </Stack>

                <Table mt="md">
                  <Table.Thead>
                    <Table.Tr>
                      <Table.Th>Field</Table.Th>
                      <Table.Th>CSV Column</Table.Th>
                    </Table.Tr>
                  </Table.Thead>
                  <Table.Tbody>
                    {[
                      { field: 'order_no', label: 'Order Number *', required: true },
                      { field: 'line_code', label: 'Line Code *', required: true },
                      { field: 'product_type_code', label: 'Product Type Code *', required: true },
                      { field: 'planned_qty', label: 'Planned Quantity *', required: true },
                      { field: 'priority', label: 'Priority', required: false },
                      { field: 'due_date', label: 'Due Date', required: false },
                      { field: 'description', label: 'Description', required: false },
                    ].map(({ field, label, required }) => (
                      <Table.Tr key={field}>
                        <Table.Td>
                          <Text fw={required ? 600 : undefined}>{label}</Text>
                        </Table.Td>
                        <Table.Td>
                          <Select
                            placeholder="Select column"
                            value={mapping[field]?.csv_column || ''}
                            onChange={(value) => handleMappingChange(field, value || '')}
                            data={uploadData?.headers || []}
                            clearable={!required}
                          />
                        </Table.Td>
                      </Table.Tr>
                    ))}
                  </Table.Tbody>
                </Table>
              </Card>

              {uploadData && (
                <Card withBorder>
                  <Text fw={600} mb="md">Preview (First 5 rows)</Text>
                  <Table striped highlightOnHover>
                    <Table.Thead>
                      <Table.Tr>
                        {uploadData.headers.map(header => (
                          <Table.Th key={header}>{header}</Table.Th>
                        ))}
                      </Table.Tr>
                    </Table.Thead>
                    <Table.Tbody>
                      {uploadData.preview.map((row, idx) => (
                        <Table.Tr key={idx}>
                          {row.map((cell, cellIdx) => (
                            <Table.Td key={cellIdx}>{cell}</Table.Td>
                          ))}
                        </Table.Tr>
                      ))}
                    </Table.Tbody>
                  </Table>
                </Card>
              )}

              <Checkbox
                label="Save this mapping as a template for future imports"
                checked={saveTemplate}
                onChange={(event) => setSaveTemplate(event.currentTarget.checked)}
              />

              {saveTemplate && (
                <TextInput
                  label="Template Name"
                  placeholder="e.g., Standard Work Order Import"
                  value={templateName}
                  onChange={(event) => setTemplateName(event.currentTarget.value)}
                  required
                />
              )}

              <Group justify="space-between" mt="xl">
                <Button variant="default" onClick={() => setActive(0)}>
                  Back
                </Button>
                <Button
                  onClick={handleExecuteImport}
                  disabled={!allRequiredMapped || (saveTemplate && !templateName)}
                  loading={executeImport.isPending}
                >
                  Start Import
                </Button>
              </Group>
            </Stack>
          </Stepper.Step>

          {/* Step 3: Import Progress */}
          <Stepper.Step label="Import" description="Processing work orders">
            <Stack gap="md" mt="xl">
              {importStatus && (
                <>
                  <Card withBorder>
                    <Stack gap="md">
                      <Group justify="space-between">
                        <Text fw={600} size="lg">Import Status</Text>
                        <Badge
                          color={
                            importStatus.status === 'COMPLETED' ? 'green' :
                            importStatus.status === 'FAILED' ? 'red' :
                            importStatus.status === 'PROCESSING' ? 'blue' : 'gray'
                          }
                          size="lg"
                        >
                          {importStatus.status}
                        </Badge>
                      </Group>

                      {(importStatus.status === 'PENDING' || importStatus.status === 'PROCESSING') && (
                        <Progress value={100} animated />
                      )}

                      <Group grow>
                        <div>
                          <Text size="sm" c="dimmed">Total Rows</Text>
                          <Text fw={600} size="xl">{importStatus.total_rows}</Text>
                        </div>
                        <div>
                          <Text size="sm" c="dimmed">Successful</Text>
                          <Text fw={600} size="xl" c="green">{importStatus.successful_rows}</Text>
                        </div>
                        <div>
                          <Text size="sm" c="dimmed">Failed</Text>
                          <Text fw={600} size="xl" c="red">{importStatus.failed_rows}</Text>
                        </div>
                      </Group>
                    </Stack>
                  </Card>

                  {importStatus.error_log && importStatus.error_log.length > 0 && (
                    <Card withBorder>
                      <Text fw={600} mb="md">Errors</Text>
                      <Stack gap="xs">
                        {importStatus.error_log.slice(0, 10).map((error, idx) => (
                          <Alert key={idx} icon={<IconAlertCircle size={16} />} color="red">
                            <Text size="sm">
                              <strong>Row {error.row}:</strong> {error.error}
                            </Text>
                          </Alert>
                        ))}
                        {importStatus.error_log.length > 10 && (
                          <Text size="sm" c="dimmed">
                            ... and {importStatus.error_log.length - 10} more errors
                          </Text>
                        )}
                      </Stack>
                    </Card>
                  )}

                  {importStatus.status === 'COMPLETED' && (
                    <Alert icon={<IconCheck size={18} />} title="Import Completed" color="green">
                      Successfully imported {importStatus.successful_rows} work orders.
                      {importStatus.failed_rows > 0 && ` ${importStatus.failed_rows} rows failed.`}
                    </Alert>
                  )}

                  {importStatus.status === 'FAILED' && (
                    <Alert icon={<IconX size={18} />} title="Import Failed" color="red">
                      The import process encountered an error and could not complete.
                    </Alert>
                  )}
                </>
              )}

              {importStatus?.status === 'COMPLETED' && (
                <Group justify="center" mt="xl">
                  <Button onClick={() => window.location.reload()}>
                    Import Another File
                  </Button>
                </Group>
              )}
            </Stack>
          </Stepper.Step>
        </Stepper>
      </Stack>
    </Container>
  );
}
