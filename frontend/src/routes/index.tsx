import { createBrowserRouter } from 'react-router-dom';
import { AppLayout } from '../components/AppLayout';
import { HomePage } from '../pages/HomePage';
import { NotFoundPage } from '../pages/NotFoundPage';
import { ValuesJournalPage } from '../pages/ValuesJournalPage';

export const router = createBrowserRouter([
  {
    path: '/',
    element: <AppLayout />,
    children: [
      { index: true, element: <HomePage /> },
      { path: 'values/journal', element: <ValuesJournalPage /> },
      { path: '*', element: <NotFoundPage /> }
    ],
  },
]);
