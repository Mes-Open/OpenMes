import AppLayout from '../../../layouts/AppLayout';
import UsersIndex from './Index';
export default function Create() { return <UsersIndex initiallyCreating />; }
Create.layout = page => <AppLayout>{page}</AppLayout>;
