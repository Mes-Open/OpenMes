import AppLayout from '../../../layouts/AppLayout';
import ProcessTemplatesIndex from './Index';
export default function Create() { return <ProcessTemplatesIndex initiallyCreating />; }
Create.layout = page => <AppLayout>{page}</AppLayout>;
